<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() !== null) {
                $user->currentAccessToken()->delete();
            }

            throw new AccessDeniedHttpException('Account is inactive.');
        }

        return $next($request);
    }
}
