<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Ibrohim\Changelog\Tests\TestCase;
use Ibrohim\Changelog\Models\ChangelogEntry;

class ImportCommandTest extends TestCase
{
    public function test_import_command_fails_if_repo_not_in_format(): void
    {
        $this->artisan('changelog:import', ['repo' => 'invalid-repo-format'])
            ->expectsOutputToContain('Repository must be in "owner/repo" format.')
            ->assertFailed();
    }

    public function test_import_command_fails_if_repo_not_registered(): void
    {
        $this->artisan('changelog:import', ['repo' => 'test-owner/unregistered'])
            ->expectsOutputToContain('Repository "test-owner/unregistered" is not registered locally.')
            ->assertFailed();
    }

    public function test_import_command_fails_if_limit_invalid(): void
    {
        $this->createRepository(['name' => 'test-owner/test-repo', 'owner' => 'test-owner', 'repo' => 'test-repo']);

        $this->artisan('changelog:import', ['repo' => 'test-owner/test-repo', '--limit' => '200'])
            ->expectsOutputToContain('Limit must be between 1 and 100.')
            ->assertFailed();
    }

    public function test_import_command_handles_api_failure(): void
    {
        $this->createRepository(['name' => 'test-owner/test-repo', 'owner' => 'test-owner', 'repo' => 'test-repo']);

        Http::fake([
            'api.github.com/repos/test-owner/test-repo/commits*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->artisan('changelog:import', ['repo' => 'test-owner/test-repo'])
            ->expectsOutputToContain('Failed to fetch commits from GitHub API. HTTP 404')
            ->assertFailed();
    }

    public function test_import_command_handles_empty_commits(): void
    {
        $this->createRepository(['name' => 'test-owner/test-repo', 'owner' => 'test-owner', 'repo' => 'test-repo']);

        Http::fake([
            'api.github.com/repos/test-owner/test-repo/commits*' => Http::response([], 200),
        ]);

        $this->artisan('changelog:import', ['repo' => 'test-owner/test-repo'])
            ->expectsOutputToContain('No commits found on that branch.')
            ->assertSuccessful();
    }

    public function test_import_command_successfully_imports_commits(): void
    {
        $this->createRepository(['name' => 'test-owner/test-repo', 'owner' => 'test-owner', 'repo' => 'test-repo']);

        Http::fake([
            'api.github.com/repos/test-owner/test-repo/commits*' => Http::response([
                [
                    'sha' => str_repeat('a', 40),
                    'commit' => [
                        'message' => "feat: new cool feature\n\nBody here",
                        'author' => ['name' => 'Alice', 'email' => 'alice@test.com', 'date' => now()->toIso8601String()]
                    ]
                ],
                [
                    'sha' => str_repeat('b', 40),
                    'commit' => [
                        'message' => 'fix: bug resolved',
                        'author' => ['name' => 'Bob', 'email' => 'bob@test.com', 'date' => now()->toIso8601String()]
                    ]
                ],
            ], 200),
        ]);

        $this->artisan('changelog:import', ['repo' => 'test-owner/test-repo'])
            ->expectsOutputToContain('Import completed successfully!')
            ->assertSuccessful();

        $this->assertDatabaseCount('changelog_entries', 2);

        // Verify the newest commit (feat) was mapped
        $this->assertDatabaseHas('changelog_entries', [
            'commit_sha' => str_repeat('a', 40),
            'title' => 'feat: new cool feature',
            'body' => 'Body here',
            'type' => 'added',
            'author_name' => 'Alice',
            'is_published' => false,
        ]);

        // Verify the older commit (fix) was mapped
        $this->assertDatabaseHas('changelog_entries', [
            'commit_sha' => str_repeat('b', 40),
            'title' => 'fix: bug resolved',
            'type' => 'fixed',
            'author_name' => 'Bob',
            'is_published' => false,
        ]);
    }
}
