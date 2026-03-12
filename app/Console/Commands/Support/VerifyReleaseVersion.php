<?php

declare(strict_types=1);

namespace App\Console\Commands\Support;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class VerifyReleaseVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-release-version {--skip-prompt : Skip interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind to bump NativePHP release version values.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $nativeVersion = trim((string) config('nativephp.version', ''));
        $nativeVersionCode = (int) config('nativephp.version_code', 0);

        $this->line('NativePHP release versions:');
        $this->line(' - NATIVEPHP_APP_VERSION: '.($nativeVersion !== '' ? $nativeVersion : '(empty)'));
        $this->line(' - NATIVEPHP_APP_VERSION_CODE: '.$nativeVersionCode);

        $warnings = [];

        if ($nativeVersion === '' || strcasecmp($nativeVersion, 'debug') === 0) {
            $warnings[] = 'NATIVEPHP_APP_VERSION looks unset or still DEBUG.';
        }

        if ($nativeVersionCode <= 1) {
            $warnings[] = 'NATIVEPHP_APP_VERSION_CODE looks unchanged (<= 1).';
        }

        if ($warnings !== []) {
            $this->warn('Release version reminder:');
            foreach ($warnings as $warning) {
                $this->line(' - '.$warning);
            }
        } else {
            $this->info('Release version reminder: looks good.');
        }

        if ($this->option('skip-prompt') || ! $this->input->isInteractive()) {
            return self::SUCCESS;
        }

        $confirmed = confirm(
            'Have you bumped app versions in .env and .env.example files for this release?',
            default: false,
        );

        if (! $confirmed) {
            $this->error('Release version check failed. Please bump the version values.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
