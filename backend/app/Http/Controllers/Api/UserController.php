<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserAccountService $userAccountService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = User::query()->orderBy('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'items' => UserResource::collection($paginator->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $created = $this->userAccountService->createAccount($actor, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully.',
            'data' => (new UserResource($created))->resolve($request),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->userAccountService->updateAccount($actor, $user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User account updated successfully.',
            'data' => (new UserResource($updated))->resolve($request),
        ], 200);
    }
}
