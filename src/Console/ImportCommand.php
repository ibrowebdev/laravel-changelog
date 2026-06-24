<?php

namespace Ibrohim\Changelog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Ibrohim\Changelog\ChangelogManager;
use Ibrohim\Changelog\Jobs\ProcessGitHubPushJob;

class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'changelog:import
                            {repo : GitHub repository in "owner/repo" format}
                            {--limit=50 : Maximum number of commits to fetch (1-100 per request)}
                            {--token= : GitHub personal access token (recommended for private repos or rate limits)}
                            {--branch= : Specific branch to fetch commits from (defaults to repo default_branch)}';

    /**
     * The console command description.
     */
    protected $description = 'Import historical commits from a GitHub repository via the REST API.';

    /**
     * Execute the console command.
     */
    public function handle(ChangelogManager $manager): int
    {
        $repoInput = $this->argument('repo');

        if (!str_contains($repoInput, '/')) {
            $this->components->error('Repository must be in "owner/repo" format.');
            return self::FAILURE;
        }

        [$owner, $repoName] = explode('/', $repoInput, 2);

        // Find the repository in the local database
        $repository = $manager->findRepository($owner, $repoName);

        if (!$repository) {
            $this->components->error("Repository \"{$repoInput}\" is not registered locally.");
            $this->line('Run <comment>php artisan changelog:install</comment> to register it first.');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 100) {
            $this->components->error('Limit must be between 1 and 100.');
            return self::FAILURE;
        }

        $branch = $this->option('branch') ?: $repository->default_branch;
        $token = $this->option('token');

        $this->components->info("Fetching up to {$limit} historical commits from {$repoInput} (branch: {$branch})...");

        // Call GitHub API
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Laravel-Changelog-Package',
        ]);

        if ($token) {
            $request->withToken($token);
        }

        $response = $request->get("https://api.github.com/repos/{$owner}/{$repoName}/commits", [
            'sha' => $branch,
            'per_page' => $limit,
        ]);

        if ($response->failed()) {
            $this->components->error("Failed to fetch commits from GitHub API. HTTP {$response->status()}");
            $this->line($response->json('message') ?? $response->body());
            return self::FAILURE;
        }

        $commits = $response->json();

        if (empty($commits)) {
            $this->components->warn('No commits found on that branch.');
            return self::SUCCESS;
        }

        $this->components->info('Mapping ' . count($commits) . ' commits...');

        // Map GitHub API response to webhook payload format
        $mappedCommits = array_map(function ($item) {
            return [
                'id' => $item['sha'],
                'message' => $item['commit']['message'] ?? '',
                'author' => [
                    'name' => $item['commit']['author']['name'] ?? 'Unknown',
                    'email' => $item['commit']['author']['email'] ?? 'unknown@example.com',
                ],
                'timestamp' => $item['commit']['author']['date'] ?? now()->toIso8601String(),
            ];
        }, $commits);

        // Reverse the array so we process oldest to newest (to maintain chronological order in the DB)
        // because GitHub returns newest first.
        $mappedCommits = array_reverse($mappedCommits);

        $this->components->task('Processing commits', function () use ($repository, $mappedCommits) {
            // Dispatch synchronously so it runs right here in the console
            ProcessGitHubPushJob::dispatchSync($repository->id, $mappedCommits);
        });

        $this->newLine();
        $this->components->info('Import completed successfully!');
        $this->line('The commits have been added to your changelog as <comment>draft</comment> entries.');
        
        $prefix = config('changelog.route_prefix', 'changelog');
        $this->line("Visit <comment>/{$prefix}/dashboard</comment> to review and publish them.");

        return self::SUCCESS;
    }
}
