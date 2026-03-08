<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JsErrorReport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<JsErrorReport>
 */
class JsErrorReportFactory extends Factory
{
    protected $model = JsErrorReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $message = fake()->sentence();
        $time = Carbon::now()->subSeconds(fake()->numberBetween(0, 120))->toIso8601String();

        return [
            'user_note' => fake()->paragraph(),
            'errors' => [[
                'type' => 'error',
                'time' => $time,
                'message' => $message,
                'source' => fake()->url(),
                'line' => fake()->numberBetween(1, 999),
                'column' => fake()->numberBetween(1, 999),
                'stack' => null,
            ]],
            'first_error_message' => $message,
            'error_count' => 1,
            'fingerprint' => fake()->sha256(),
            'page_url' => fake()->url(),
            'user_agent' => fake()->userAgent(),
            'client_language' => fake()->languageCode(),
            'runtime_platform' => fake()->randomElement(['Web - android', 'Web - ios']),
            'screen_breakpoint' => fake()->randomElement(['base', 'sm', 'md', 'lg', 'xl', '2xl']),
            'app_version' => 'DEBUG',
            'ip_address' => fake()->ipv4(),
            'first_occurred_at' => Carbon::parse($time),
            'last_occurred_at' => Carbon::parse($time),
        ];
    }
}
