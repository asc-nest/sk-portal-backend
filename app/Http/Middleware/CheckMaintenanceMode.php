<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SystemSetting;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isMaintenance = (bool) SystemSetting::get('maintenance_mode', false);

        if ($isMaintenance) {
            $user = $request->user();

            // Allow Super Admins through during maintenance
            if ($user && ($user->hasRole('super_admin') || $user->username === 'superadmin')) {
                return $next($request);
            }

            // Exclude public settings and admin login check
            if ($request->is('api/v1/system/settings') || $request->is('api/v1/admin/*')) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'The SK Portal is currently under maintenance. Only Super Administrators can access the system at this time.',
                'error_code' => 'MAINTENANCE_MODE_ACTIVE',
            ], 503);
        }

        return $next($request);
    }
}

