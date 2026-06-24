<?php

namespace Ibrohim\Changelog\Tests;

use Ibrohim\Changelog\ChangelogServiceProvider;
use Ibrohim\Changelog\Facades\Changelog;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Set up the test environment.
     *
     * Orchestra Testbench provides a full Laravel environment for package
     * testing. This setUp() runs migrations so every test starts with
     * fresh tables in the in-memory SQLite database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run the package migrations against the test database
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register the package service providers.
     *
     * Testbench needs to know which providers to boot. This is the
     * equivalent of adding the provider to config/app.php.
     */
    protected function getPackageProviders($app): array
    {
        return [
            ChangelogServiceProvider::class,
        ];
    }

    /**
     * Register the package Facade aliases.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Changelog' => Changelog::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * Configure the test environment: SQLite in-memory, sync queue,
     * and a test APP_KEY for the encrypted webhook_secret cast.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Test Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a test repository record.
     */
    protected function createRepository(array $overrides = []): \Ibrohim\Changelog\Models\ChangelogRepository
    {
        return \Ibrohim\Changelog\Models\ChangelogRepository::create(array_merge([
            'name' => 'test-owner/test-repo',
            'github_id' => 123456,
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'default_branch' => 'main',
            'webhook_secret' => 'test-secret-123',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Create a test changelog entry.
     */
    protected function createEntry(array $overrides = []): \Ibrohim\Changelog\Models\ChangelogEntry
    {
        if (!isset($overrides['changelog_repository_id'])) {
            $repo = \Ibrohim\Changelog\Models\ChangelogRepository::first() ?? $this->createRepository();
            $overrides['changelog_repository_id'] = $repo->id;
        }

        return \Ibrohim\Changelog\Models\ChangelogEntry::create(array_merge([
            'title' => 'Test entry',
            'body' => 'Test body content',
            'original_commit_message' => 'test: original commit message',
            'commit_sha' => bin2hex(random_bytes(20)),
            'author_name' => 'Test Author',
            'author_email' => 'test@example.com',
            'type' => 'added',
            'is_published' => false,
            'committed_at' => now(),
        ], $overrides));
    }

    /**
     * Build a valid GitHub push webhook payload.
     */
    protected function buildPushPayload(array $overrides = [], array $commits = []): array
    {
        if (empty($commits)) {
            $commits = [
                [
                    'id' => 'abc123def456abc123def456abc123def456abc1',
                    'message' => "feat: add dark mode\n\nImplemented dark mode toggle in the settings panel.",
                    'timestamp' => now()->toIso8601String(),
                    'author' => [
                        'name' => 'Test Developer',
                        'email' => 'dev@example.com',
                    ],
                    'url' => 'https://github.com/test-owner/test-repo/commit/abc123',
                ],
            ];
        }

        return array_merge([
            'ref' => 'refs/heads/main',
            'repository' => [
                'id' => 123456,
                'name' => 'test-repo',
                'full_name' => 'test-owner/test-repo',
                'owner' => [
                    'login' => 'test-owner',
                ],
            ],
            'commits' => $commits,
        ], $overrides);
    }

    /**
     * Generate the HMAC signature header for a payload.
     */
    protected function signPayload(string $payload, string $secret = 'test-secret-123'): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
}
