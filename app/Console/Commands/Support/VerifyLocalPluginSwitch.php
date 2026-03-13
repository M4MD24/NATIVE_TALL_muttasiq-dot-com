<?php

declare(strict_types=1);

namespace App\Console\Commands\Support;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class VerifyLocalPluginSwitch extends Command
{
    private const PACKAGE_NAME = 'goodm4ven/nativephp-muttasiq-patches';

    private const REPOSITORY_NAME = 'nativephp-muttasiq-patches';

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

        $matchingRepository = $this->findMatchingRepository($decoded['repositories'] ?? null);
        $isLocalPathRepositoryEnabled = $matchingRepository !== null;

        $this->line('Local plugin switch check:');
        $this->line(' - package: '.self::PACKAGE_NAME);
        $this->line(' - repository key: '.self::REPOSITORY_NAME);
        $this->line(
            ' - local path repository: '.($isLocalPathRepositoryEnabled ? 'ENABLED' : 'disabled'),
        );

        if ($matchingRepository !== null) {
            $this->line(' - repository url: '.(string) ($matchingRepository['url'] ?? '(missing)'));
        }

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
            'Local path plugin repository for '.self::PACKAGE_NAME.' is still enabled. Continue anyway?',
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

    /**
     * @return array<string, mixed>|null
     */
    private function findMatchingRepository(mixed $repositories): ?array
    {
        if (! is_array($repositories)) {
            return null;
        }

        if (! array_is_list($repositories)) {
            $repository = $repositories[self::REPOSITORY_NAME] ?? null;

            if (is_array($repository) && strtolower((string) ($repository['type'] ?? '')) === 'path') {
                return $repository;
            }
        }

        foreach ($repositories as $repository) {
            if ($this->isMatchingPathRepository($repository)) {
                return $repository;
            }
        }

        return null;
    }

    private function isMatchingPathRepository(mixed $repository): bool
    {
        if (! is_array($repository)) {
            return false;
        }

        if (strtolower((string) ($repository['type'] ?? '')) !== 'path') {
            return false;
        }

        if (strtolower((string) ($repository['name'] ?? '')) === self::REPOSITORY_NAME) {
            return true;
        }

        $versions = $repository['options']['versions'] ?? null;

        return is_array($versions) && array_key_exists(self::PACKAGE_NAME, $versions);
    }
}
