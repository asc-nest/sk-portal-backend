<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log an administrative activity.
     */
    public static function log(
        string $action,
        string $description,
        ?object $target = null,
        ?array $properties = null,
        ?int $userId = null
    ): ActivityLog {
        $user = auth()->user();
        $resolvedUserId = $userId ?? ($user ? $user->id : null);

        $targetType = null;
        $targetId = null;
        $targetName = null;

        if ($target) {
            $targetType = get_class($target);
            $targetId = $target->id ?? null;
            $targetName = $target->name ?? $target->title ?? $target->full_name ?? ($target->email ?? null);
        }

        return ActivityLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'description' => $description,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 250),
            'created_at' => now(),
        ]);
    }
}

