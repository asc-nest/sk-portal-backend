<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminActivityLogController extends Controller
{
    /**
     * Retrieve paginated administrative activity audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email,username');

        // Search by keyword
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('target_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by action
        if ($request->filled('action') && $request->query('action') !== 'all') {
            $query->where('action', $request->query('action'));
        }

        // Filter by specific user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->query('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        // Distinct actions for filter pills
        $distinctActions = ActivityLog::distinct()->pluck('action')->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'available_actions' => $distinctActions,
            ],
        ]);
    }
}

