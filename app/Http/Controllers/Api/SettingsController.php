<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settingDefaults = Setting::defaults();
        $storedSettings = Setting::query()
            ->whereIn('name', array_keys($settingDefaults))
            ->pluck('value', 'name')
            ->all();

        return response()->json([
            'settings' => Setting::normalizeSettings(
                array_replace($settingDefaults, $storedSettings),
            ),
            'mainTextSizeLimits' => Setting::mainTextSizeLimits(),
        ]);
    }
}
