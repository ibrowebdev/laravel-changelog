<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Tests\TestCase;

class WebhookTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────
    //  Middleware: Signature Verification
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_rejects_missing_signature_header(): void
    {
        $this->createRepository();
        $payload = $this->buildPushPayload();

        $response = $this->postJson(
            '/changelog/webhook',
            $payload,
            // No X-Hub-Signature-256 header
        );

        $response->assertStatus(403);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->createRepository();
        $payload = $this->buildPushPayload();
        $json = json_encode($payload);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => 'sha256=invalidsignature',
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(403);
    }

    public function test_webhook_rejects_unknown_repository(): void
    {
        // No repo created — the payload references a non-existent repo
        $payload = $this->buildPushPayload();
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(404);
    }

    public function test_webhook_rejects_inactive_repository(): void
    {
        $this->createRepository(['is_active' => false]);
        $payload = $this->buildPushPayload();
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Controller: Event Handling
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_accepts_valid_push_event(): void
    {
        $this->createRepository();
        $payload = $this->buildPushPayload();
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Webhook received. Processing 1 commit(s).']);
    }

    public function test_webhook_ignores_non_push_events(): void
    {
        $this->createRepository();
        $payload = $this->buildPushPayload();
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'pull_request',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => "Event 'pull_request' ignored. Only 'push' events are processed."]);
    }

    public function test_webhook_ignores_non_default_branch(): void
    {
        $this->createRepository(['default_branch' => 'main']);
        $payload = $this->buildPushPayload(['ref' => 'refs/heads/feature/login']);
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => "Branch 'feature/login' ignored. Only 'main' is tracked."]);
    }

    public function test_webhook_creates_entries_for_commits(): void
    {
        $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('a', 40),
                'message' => "feat: add user avatars\n\nUsers can now upload profile pictures.",
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            ],
            [
                'id' => str_repeat('b', 40),
                'message' => 'fix: resolve login crash',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
        ];

        $payload = $this->buildPushPayload([], $commits);
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $response = $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $response->assertStatus(200);

        // Since queue is sync, the job runs immediately
        $this->assertDatabaseCount('changelog_entries', 2);

        $this->assertDatabaseHas('changelog_entries', [
            'title' => 'feat: add user avatars',
            'author_name' => 'Alice',
            'type' => 'added',
        ]);

        $this->assertDatabaseHas('changelog_entries', [
            'title' => 'fix: resolve login crash',
            'author_name' => 'Bob',
            'type' => 'fixed',
        ]);
    }

    public function test_webhook_is_idempotent_on_retry(): void
    {
        $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('c', 40),
                'message' => 'feat: new feature',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@example.com'],
            ],
        ];

        $payload = $this->buildPushPayload([], $commits);
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $headers = [
            'HTTP_X-Hub-Signature-256' => $signature,
            'HTTP_X-GitHub-Event' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ];

        // Send the same webhook twice
        $this->call('POST', '/changelog/webhook', [], [], [], $headers, $json);
        $this->call('POST', '/changelog/webhook', [], [], [], $headers, $json);

        // Should still only have 1 entry (updateOrCreate)
        $this->assertDatabaseCount('changelog_entries', 1);
    }

    public function test_entries_are_created_as_drafts(): void
    {
        $this->createRepository();

        $commits = [
            [
                'id' => str_repeat('d', 40),
                'message' => 'feat: something new',
                'timestamp' => now()->toIso8601String(),
                'author' => ['name' => 'Dev', 'email' => 'dev@example.com'],
            ],
        ];

        $payload = $this->buildPushPayload([], $commits);
        $json = json_encode($payload);
        $signature = $this->signPayload($json);

        $this->call(
            'POST',
            '/changelog/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $signature,
                'HTTP_X-GitHub-Event' => 'push',
                'CONTENT_TYPE' => 'application/json',
            ],
            $json,
        );

        $entry = ChangelogEntry::first();
        $this->assertFalse($entry->is_published);
        $this->assertNull($entry->published_at);
    }
}
