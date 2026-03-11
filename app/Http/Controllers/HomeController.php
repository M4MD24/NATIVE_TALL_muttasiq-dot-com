<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Thikr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        if (is_platform('mobile')) {
            Setting::setAppVersion(Setting::configuredAppVersion());
        }

        $settingsPayload = $this->resolveSettingsPayload();

        return view('home', [
            'athkar' => $this->resolveAthkarPayload(),
            'athkarSettings' => $settingsPayload['settings'],
            'athkarMainTextSizeLimits' => $settingsPayload['mainTextSizeLimits'],
        ]);
    }

    /**
     * @return array{settings: array<string, bool|int>, mainTextSizeLimits: array<string, array{min: int, max: int, default: int}>}
     */
    private function resolveSettingsPayload(): array
    {
        if (is_platform('mobile')) {
            $remotePayload = $this->fetchRemoteSettingsPayload();

            if ($remotePayload !== null) {
                return $remotePayload;
            }
        }

        return $this->resolveLocalSettingsPayload();
    }

    /**
     * @return array{settings: array<string, bool|int>, mainTextSizeLimits: array<string, array{min: int, max: int, default: int}>}
     */
    private function resolveLocalSettingsPayload(): array
    {
        $settingDefaults = Setting::defaults();
        $storedSettings = Setting::query()
            ->whereIn('name', array_keys($settingDefaults))
            ->pluck('value', 'name')
            ->all();

        return [
            'settings' => Setting::normalizeSettings(
                array_replace($settingDefaults, $storedSettings),
            ),
            'mainTextSizeLimits' => Setting::mainTextSizeLimits(),
        ];
    }

    /**
     * @return array{settings: array<string, bool|int>, mainTextSizeLimits: array<string, array{min: int, max: int, default: int}>}|null
     */
    private function fetchRemoteSettingsPayload(): ?array
    {
        $url = $this->resolveSettingsApiUrl();

        if ($url === null) {
            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::acceptJson()
                ->timeout((int) config('app.custom.native_end_points.retries', 8))
                ->get($url);

            if ($response->successful()) {
                $settings = $response->json('settings');
                $limits = $response->json('mainTextSizeLimits');
                $remoteAppVersion = $response->json('appVersion');

                if (is_string($remoteAppVersion) && trim($remoteAppVersion) !== '') {
                    Setting::setAppVersion($remoteAppVersion);
                }

                if (is_array($settings) && is_array($limits)) {
                    return [
                        'settings' => Setting::normalizeSettings($settings),
                        'mainTextSizeLimits' => $limits,
                    ];
                }

                Log::warning('Settings API returned an invalid payload.', [
                    'url' => $url,
                ]);
            }

            Log::warning('Settings API returned non-success response.', [
                'status' => $response->status(),
                'url' => $url,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Settings API request failed.', [
                'message' => $exception->getMessage(),
                'url' => $url,
            ]);
        }

        return null;
    }

    private function resolveSettingsApiUrl(): ?string
    {
        $configuredSettingsEndpoint = (string) config('app.custom.native_end_points.settings', 'settings');

        if (str_starts_with($configuredSettingsEndpoint, 'https://') || str_starts_with($configuredSettingsEndpoint, 'http://')) {
            return $configuredSettingsEndpoint;
        }

        $applicationUrl = rtrim((string) config('app.url'), '/');
        $applicationUrlScheme = parse_url($applicationUrl, PHP_URL_SCHEME);

        if (! is_string($applicationUrlScheme) || ! in_array(strtolower($applicationUrlScheme), ['http', 'https'], true)) {
            Log::warning('Skipping Settings API request because APP_URL does not use an HTTP scheme.', [
                'app_url' => $applicationUrl,
            ]);

            return null;
        }

        $relativeSettingsPath = route('api.settings.index', [], false);

        return $applicationUrl.$relativeSettingsPath;
    }

    /**
     * @return array<int, array{id: int, time: string, type: string, text: string, origin: string|null, is_aayah: bool, is_original: bool, count: int, order: int}>
     */
    private function resolveAthkarPayload(): array
    {
        if (! is_platform('mobile')) {
            return Thikr::defaultsPayload();
        }

        $url = $this->resolveAthkarApiUrl();
        if ($url === null) {
            return Thikr::defaultsPayload();
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::acceptJson()
                ->timeout((int) config('app.custom.native_end_points.retries', 8))
                ->get($url);

            if ($response->successful()) {
                if (is_array($athkar = $response->json('athkar'))) {
                    return $athkar;
                }

                Log::warning('Athkar API returned an invalid payload.', [
                    'url' => $url,
                ]);
            }

            Log::warning('Athkar API returned non-success response.', [
                'status' => $response->status(),
                'url' => $url,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Athkar API request failed.', [
                'message' => $exception->getMessage(),
                'url' => $url,
            ]);
        }

        return Thikr::defaultsPayload();
    }

    private function resolveAthkarApiUrl(): ?string
    {
        $configuredAthkarEndpoint = (string) config('app.custom.native_end_points.athkar', 'athkar');

        if (str_starts_with($configuredAthkarEndpoint, 'https://') || str_starts_with($configuredAthkarEndpoint, 'http://')) {
            return $configuredAthkarEndpoint;
        }

        $applicationUrl = rtrim((string) config('app.url'), '/');
        $applicationUrlScheme = parse_url($applicationUrl, PHP_URL_SCHEME);

        if (! is_string($applicationUrlScheme) || ! in_array(strtolower($applicationUrlScheme), ['http', 'https'], true)) {
            Log::warning('Skipping Athkar API request because APP_URL does not use an HTTP scheme.', [
                'app_url' => $applicationUrl,
            ]);

            return null;
        }

        $relativeAthkarPath = route('api.athkar.index', [], false);

        return $applicationUrl.$relativeAthkarPath;
    }
}
