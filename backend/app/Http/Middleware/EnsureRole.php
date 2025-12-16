<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', null, 401);
        }

        if (!in_array($user->role, $roles)) {
            return ApiResponse::forbidden('You do not have permission to access this resource');
        }

        return $next($request);
    }
}
