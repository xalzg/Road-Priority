<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));
        $password = $validated['password'];
        $deviceName = $validated['device_name'] ?? 'web-client';

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || !Hash::check($password, $user->password)) {
            $this->auditLogService->record(
                actor: $user,
                action: 'auth.login_failed',
                entityType: 'user',
                entityId: $user?->id,
                changesSummary: [
                    'email' => $email,
                    'password' => $password,
                    'reason' => 'invalid_credentials',
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'errors' => null,
            ], 401);
        }

        if (!$user->is_active) {
            $this->auditLogService->record(
                actor: $user,
                action: 'auth.login_blocked_inactive',
                entityType: 'user',
                entityId: $user->id,
                changesSummary: [
                    'email' => $email,
                    'reason' => 'account_inactive',
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
                'errors' => null,
            ], 403);
        }

        $newAccessToken = $user->createToken($deviceName);
        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = is_numeric($expirationMinutes) && (int) $expirationMinutes > 0
            ? now()->addMinutes((int) $expirationMinutes)->toIso8601String()
            : null;

        $this->auditLogService->record(
            actor: $user,
            action: 'auth.login_succeeded',
            entityType: 'user',
            entityId: $user->id,
            changesSummary: [
                'device_name' => $deviceName,
                'token_id' => $newAccessToken->accessToken->id,
                'expires_at' => $expiresAt,
                'ip_address' => $request->ip(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'data' => [
                'token' => $newAccessToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt,
                'user' => (new UserResource($user))->resolve($request),
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();
        $tokenId = null;

        if ($token instanceof PersonalAccessToken) {
            $tokenId = $token->id;
            $token->delete();
        }

        $this->auditLogService->record(
            actor: $user,
            action: 'auth.logout',
            entityType: 'user',
            entityId: $user->id,
            changesSummary: [
                'revoked_token_id' => $tokenId,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => null,
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Current user profile retrieved.',
            'data' => (new UserResource($user))->resolve($request),
        ], 200);
    }
}
