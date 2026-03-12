<?php

declare(strict_types=1);

namespace App\Console\Commands\Support;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class VerifyLocalPluginSwitch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-local-plugin-switch
        {--skip-prompt : Skip interactive confirmation}
        {--composer-file=composer.json : Composer manifest path to inspect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warn when local Composer path plugin overrides are still enabled.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $composerFileOption = trim((string) $this->option('composer-file'));
        $composerFilePath = $this->resolveComposerFilePath($composerFileOption);

        if (! is_file($composerFilePath) || ! is_readable($composerFilePath)) {
            $this->error('Unable to read composer manifest at: '.$composerFilePath);

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($composerFilePath), true);

        if (! is_array($decoded)) {
            $this->error('Invalid composer manifest JSON: '.$composerFilePath);

            return self::FAILURE;
        }

        $packageName = 'goodm4ven/nativephp-muttasiq-patches';
        $repositoryKey = 'nativephp-muttasiq-patches';
        $repository = $decoded['repositories'][$repositoryKey] ?? null;
        $repositoryType = strtolower((string) ($repository['type'] ?? ''));
        $isLocalPathRepositoryEnabled = is_array($repository) && $repositoryType === 'path';

        $this->line('Local plugin switch check:');
        $this->line(' - package: '.$packageName);
        $this->line(' - repository key: '.$repositoryKey);
        $this->line(
            ' - local path repository: '.($isLocalPathRepositoryEnabled ? 'ENABLED' : 'disabled'),
        );

        if (! $isLocalPathRepositoryEnabled) {
            $this->info('Local plugin switch reminder: looks good.');

            return self::SUCCESS;
        }

        $this->warn('Local plugin switch reminder:');
        $this->line(' - Local path plugin override appears enabled in composer repositories.');
        $this->line(' - If this is a release check, you likely meant to toggle it off first.');

        if ($this->option('skip-prompt') || ! $this->input->isInteractive()) {
            return self::SUCCESS;
        }

        $confirmed = confirm(
            'Local path plugin repository for goodm4ven/nativephp-muttasiq-patches is still enabled. Continue anyway?',
            default: false,
        );

        if (! $confirmed) {
            $this->error(
                'Local plugin switch check failed. Run .scripts/composer-local-plugins-switch.sh before continuing.',
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveComposerFilePath(string $composerFileOption): string
    {
        if ($composerFileOption === '') {
            return base_path('composer.json');
        }

        if (str_starts_with($composerFileOption, DIRECTORY_SEPARATOR)) {
            return $composerFileOption;
        }

        return base_path($composerFileOption);
    }
}
