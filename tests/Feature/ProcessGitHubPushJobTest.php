<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Jobs\ProcessGitHubPushJob;
use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Tests\TestCase;

class ProcessGitHubPushJobTest extends TestCase
{
    public function test_job_creates_entries_from_commits(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('a', 40),
                'message' => "Add user registration\n\nFull signup flow with email verification.",
                'timestamp' => '2026-06-23T12:00:00+00:00',
                'author' => ['name' => 'Alice', 'email' => 'alice@test.com'],
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $this->assertDatabaseCount('changelog_entries', 1);

        $entry = ChangelogEntry::first();
        $this->assertEquals('Add user registration', $entry->title);
        $this->assertEquals('Full signup flow with email verification.', $entry->body);
        $this->assertEquals("Add user registration\n\nFull signup flow with email verification.", $entry->original_commit_message);
        $this->assertEquals(str_repeat('a', 40), $entry->commit_sha);
        $this->assertEquals('Alice', $entry->author_name);
        $this->assertEquals('alice@test.com', $entry->author_email);
        $this->assertEquals('added', $entry->type);
        $this->assertNotNull($entry->committed_at);
    }

    public function test_job_splits_commit_message_into_title_and_body(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('b', 40),
                'message' => "First line subject\n\nParagraph body content here.",
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@test.com'],
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entry = ChangelogEntry::first();
        $this->assertEquals('First line subject', $entry->title);
        $this->assertEquals('Paragraph body content here.', $entry->body);
    }

    public function test_job_handles_single_line_commit_messages(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('c', 40),
                'message' => 'Single line commit',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@test.com'],
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entry = ChangelogEntry::first();
        $this->assertEquals('Single line commit', $entry->title);
        $this->assertNull($entry->body);
    }

    public function test_job_detects_conventional_commit_types(): void
    {
        $repo = $this->createRepository();

        $commits = [
            ['id' => str_repeat('1', 40), 'message' => 'feat: add login', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('2', 40), 'message' => 'fix: resolve crash', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('3', 40), 'message' => 'refactor: clean code', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('4', 40), 'message' => 'remove: legacy api', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('5', 40), 'message' => 'security: patch xss', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entries = ChangelogEntry::orderBy('commit_sha')->get();

        $this->assertEquals('added', $entries[0]->type);
        $this->assertEquals('fixed', $entries[1]->type);
        $this->assertEquals('changed', $entries[2]->type);
        $this->assertEquals('removed', $entries[3]->type);
        $this->assertEquals('security', $entries[4]->type);
    }

    public function test_job_detects_natural_language_types(): void
    {
        $repo = $this->createRepository();

        $commits = [
            ['id' => str_repeat('a', 40), 'message' => 'Add dark mode', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('b', 40), 'message' => 'Fix broken navbar', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('c', 40), 'message' => 'Update dependencies', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
            ['id' => str_repeat('d', 40), 'message' => 'Remove deprecated method', 'timestamp' => now()->toIso8601String(), 'author' => ['name' => 'D', 'email' => 'd@t.com']],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entries = ChangelogEntry::orderBy('commit_sha')->get();

        $this->assertEquals('added', $entries[0]->type);
        $this->assertEquals('fixed', $entries[1]->type);
        $this->assertEquals('changed', $entries[2]->type);
        $this->assertEquals('removed', $entries[3]->type);
    }

    public function test_job_returns_null_type_for_unrecognised_messages(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('e', 40),
                'message' => 'Merge pull request #42 from feature/login',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'D', 'email' => 'd@t.com'],
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entry = ChangelogEntry::first();
        $this->assertNull($entry->type);
    }

    public function test_job_handles_multiple_commits(): void
    {
        $repo = $this->createRepository();

        $commits = [];
        for ($i = 0; $i < 5; $i++) {
            $commits[] = [
                'id' => str_repeat((string) $i, 40),
                'message' => "Commit number {$i}",
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => "Dev {$i}", 'email' => "dev{$i}@test.com"],
            ];
        }

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $this->assertDatabaseCount('changelog_entries', 5);
    }

    public function test_job_is_idempotent(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('f', 40),
                'message' => 'feat: idempotent test',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@test.com'],
            ],
        ];

        // Run twice
        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);
        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $this->assertDatabaseCount('changelog_entries', 1);
    }

    public function test_job_skips_commits_without_sha(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                // Missing 'id' key
                'message' => 'No SHA commit',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@test.com'],
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $this->assertDatabaseCount('changelog_entries', 0);
    }

    public function test_job_handles_deleted_repository_gracefully(): void
    {
        // Pass a non-existent repo ID — job should not throw
        ProcessGitHubPushJob::dispatchSync(99999, [
            [
                'id' => str_repeat('a', 40),
                'message' => 'test',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@test.com'],
            ],
        ]);

        $this->assertDatabaseCount('changelog_entries', 0);
    }

    public function test_job_handles_missing_author_gracefully(): void
    {
        $repo = $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('g', 40),
                'message' => 'No author info',
                'timestamp' => now()->toIso8601String(),
                'author' => [], // empty author
            ],
        ];

        ProcessGitHubPushJob::dispatchSync($repo->id, $commits);

        $entry = ChangelogEntry::first();
        $this->assertEquals('Unknown', $entry->author_name);
        $this->assertEquals('unknown@example.com', $entry->author_email);
    }
}
