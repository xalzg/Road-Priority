<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(...$roles)) {
            throw new AccessDeniedHttpException('This action is unauthorized for your role.');
        }

        return $next($request);
    }
}

