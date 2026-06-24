<?php

namespace Ibrohim\Changelog\Console;

use Illuminate\Console\Command;
use Ibrohim\Changelog\ChangelogManager;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --repo and --secret are optional — if not provided, the command
     * will prompt for them interactively.
     */
    protected $signature = 'changelog:install
                            {--repo= : GitHub repository in "owner/repo" format}
                            {--secret= : Webhook secret for HMAC verification}
                            {--branch=main : Default branch to track}';

    /**
     * The console command description.
     */
    protected $description = 'Install the Laravel Changelog package — publishes config, runs migrations, and optionally registers a repository.';

    /**
     * Execute the console command.
     *
     * The install command does three things:
     * 1. Publishes the config file
     * 2. Runs the package migrations
     * 3. Optionally registers the first GitHub repository
     *
     * This gives the user a single command to go from zero to ready.
     */
    public function handle(ChangelogManager $manager): int
    {
        $this->components->info('Installing Laravel Changelog...');
        $this->newLine();

        // ── Step 1: Publish config ──────────────────────────────────────
        $this->components->task('Publishing configuration', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'changelog-config',
                '--force' => false,
            ]);
        });

        // ── Step 2: Run migrations ──────────────────────────────────────
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate', [
                '--force' => $this->laravel->environment('production'),
            ]);
        });

        $this->newLine();

        // ── Step 3: Register a repository (optional) ────────────────────
        if ($this->option('repo') || $this->confirm('Would you like to register a GitHub repository now?', true)) {
            $this->registerRepository($manager);
        }

        $this->newLine();
        $this->components->info('Laravel Changelog installed successfully!');
        $this->newLine();

        // ── Post-install instructions ───────────────────────────────────
        $prefix = config('changelog.route_prefix', 'changelog');

        $this->components->bulletList([
            "Dashboard: <comment>/{$prefix}/dashboard</comment>",
            "Public changelog: <comment>/{$prefix}</comment>",
            "Webhook URL: <comment>/{$prefix}/webhook</comment> (POST)",
            "Widget JS: <comment>/{$prefix}/widget.js</comment>",
        ]);

        $this->newLine();
        $this->line('  <fg=gray>Configure your GitHub webhook to point to the webhook URL above.</>');
        $this->line('  <fg=gray>Set the content type to application/json and add your secret.</>');

        return self::SUCCESS;
    }

    /**
     * Interactively register a GitHub repository.
     */
    protected function registerRepository(ChangelogManager $manager): void
    {
        // Get the repo identifier
        $repoInput = $this->option('repo')
            ?: $this->ask('GitHub repository (owner/repo)', 'your-org/your-repo');

        // Validate the format
        if (!str_contains($repoInput, '/')) {
            $this->components->error('Repository must be in "owner/repo" format.');
            return;
        }

        [$owner, $repo] = explode('/', $repoInput, 2);

        // Get the webhook secret
        $secret = $this->option('secret')
            ?: $this->secret('Webhook secret (this will be encrypted at rest)');

        if (empty($secret)) {
            $this->components->error('Webhook secret cannot be empty.');
            return;
        }

        // Get the branch
        $branch = $this->option('branch');

        // Create the repository record
        $repository = $manager->addRepository($owner, $repo, $secret, $branch);

        $this->components->info("Repository \"{$repository->name}\" registered successfully.");
        $this->components->bulletList([
            "Owner: <comment>{$owner}</comment>",
            "Repo: <comment>{$repo}</comment>",
            "Branch: <comment>{$branch}</comment>",
            'Webhook secret: <comment>[encrypted]</comment>',
        ]);
    }
}
