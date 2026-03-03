<?php

declare(strict_types=1);

namespace App\Services\JsErrorReports;

use App\Models\JsErrorReport;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JsErrorReportRecorder
{
    /**
     * @param  array{
     *     user_note: string,
     *     errors: array<int, array{
     *         type: string,
     *         time?: string|null,
     *         message: string,
     *         source?: string|null,
     *         line?: int|null,
     *         column?: int|null,
     *         stack?: string|null
     *     }>,
     *     context?: array{
     *         url?: string|null,
     *         user_agent?: string|null,
     *         language?: string|null,
     *         platform?: string|null
     *     }
     * } $payload
     */
    public function store(array $payload, Request $request): JsErrorReport
    {
        $errors = collect($payload['errors'])
            ->take(15)
            ->map(fn (array $entry): array => [
                'type' => $this->sanitizeString($entry['type'], 20) ?: 'error',
                'time' => $this->sanitizeString($entry['time'] ?? null, 50),
                'message' => $this->sanitizeString($entry['message'], 1000) ?: 'Unknown error',
                'source' => $this->sanitizeString($entry['source'] ?? null, 2048),
                'line' => is_numeric($entry['line'] ?? null) ? max(0, (int) $entry['line']) : null,
                'column' => is_numeric($entry['column'] ?? null) ? max(0, (int) $entry['column']) : null,
                'stack' => $this->sanitizeString($entry['stack'] ?? null, 12000),
            ])
            ->values()
            ->all();

        $context = $payload['context'] ?? [];
        $firstOccurredAt = $this->resolveOccurredAt($errors, pickLatest: false);
        $lastOccurredAt = $this->resolveOccurredAt($errors, pickLatest: true);
        $fingerprint = $this->resolveFingerprint($errors, $context, $request);

        return JsErrorReport::query()->create([
            'user_note' => $this->sanitizeString($payload['user_note'], 1500) ?: '',
            'errors' => $errors,
            'first_error_message' => Str::of((string) ($errors[0]['message'] ?? 'Unknown error'))->limit(500, '')->toString(),
            'error_count' => count($errors),
            'fingerprint' => $fingerprint,
            'page_url' => $this->sanitizeString($context['url'] ?? null, 2048),
            'user_agent' => $this->sanitizeString($context['user_agent'] ?? $request->userAgent(), 1000),
            'client_language' => $this->sanitizeString($context['language'] ?? null, 32),
            'runtime_platform' => $this->sanitizeString($context['platform'] ?? null, 32),
            'app_version' => $this->sanitizeString(Setting::appVersion(), 32),
            'ip_address' => $this->sanitizeString($request->ip(), 45),
            'first_occurred_at' => $firstOccurredAt,
            'last_occurred_at' => $lastOccurredAt,
        ]);
    }

    /**
     * @param  array<int, array{type: string, time: string|null, message: string, source: string|null, line: int|null, column: int|null, stack: string|null}>  $errors
     * @param  array<string, mixed>  $context
     */
    private function resolveFingerprint(array $errors, array $context, Request $request): ?string
    {
        if ($errors === []) {
            return null;
        }

        $hashable = [
            'first_message' => $errors[0]['message'] ?? null,
            'first_source' => $errors[0]['source'] ?? null,
            'first_line' => $errors[0]['line'] ?? null,
            'platform' => $context['platform'] ?? null,
            'page_url' => $context['url'] ?? null,
            'user_agent' => $context['user_agent'] ?? $request->userAgent(),
        ];

        $encoded = json_encode($hashable);

        if (! is_string($encoded)) {
            return null;
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<int, array{type: string, time: string|null, message: string, source: string|null, line: int|null, column: int|null, stack: string|null}>  $errors
     */
    private function resolveOccurredAt(array $errors, bool $pickLatest): ?CarbonImmutable
    {
        $times = collect($errors)
            ->pluck('time')
            ->filter(fn (mixed $time): bool => is_string($time) && trim($time) !== '')
            ->map(function (string $time): ?CarbonImmutable {
                try {
                    return CarbonImmutable::parse($time);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter(fn (mixed $time): bool => $time instanceof CarbonImmutable)
            ->values();

        if ($times->isEmpty()) {
            return null;
        }

        /** @var CarbonImmutable $resolved */
        $resolved = $pickLatest ? $times->max() : $times->min();

        return $resolved;
    }

    private function sanitizeString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim(strip_tags($value));

        if ($trimmed === '') {
            return null;
        }

        return (string) Str::of($trimmed)->limit($maxLength, '');
    }
}
