<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated access.',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $isSuperAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        if (!$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Super Administrator privileges required.',
                'error_code' => 'FORBIDDEN_SUPER_ADMIN_ONLY',
            ], 403);
        }

        return $next($request);
    }
}

