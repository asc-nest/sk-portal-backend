<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    /**
     * Retrieve all portal configuration settings.
     */
    public function index(): JsonResponse
    {
        $settings = SystemSetting::all();
        $grouped = $settings->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'grouped' => $grouped,
            ],
        ]);
    }

    /**
     * Update portal configuration settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['nullable'],
        ]);

        $updatedKeys = [];
        foreach ($validated['settings'] as $item) {
            SystemSetting::set($item['key'], $item['value']);
            $updatedKeys[] = $item['key'];
        }

        ActivityLogger::log(
            'settings_updated',
            'Super Administrator updated portal system settings: ' . implode(', ', $updatedKeys),
            null,
            ['updated_keys' => $updatedKeys]
        );

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully.',
            'data' => SystemSetting::all()->groupBy('group'),
        ]);
    }

    /**
     * Retrieve public system settings dictionary for application-wide behavior.
     */
    public function publicSettings(): JsonResponse
    {
        $settings = SystemSetting::all();
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting->key] = $setting->parsed_value;
        }

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }
}

