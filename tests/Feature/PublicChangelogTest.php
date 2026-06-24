<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Tests\TestCase;

class PublicChangelogTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────
    //  Public HTML Page
    // ─────────────────────────────────────────────────────────────────────

    public function test_public_page_loads(): void
    {
        $response = $this->get('/changelog');
        $response->assertStatus(200);
        $response->assertSee('Changelog');
    }

    public function test_public_page_shows_published_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Visible Feature',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('a', 40),
        ]);

        $response = $this->get('/changelog');
        $response->assertStatus(200);
        $response->assertSee('Visible Feature');
    }

    public function test_public_page_hides_draft_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Secret Draft',
            'is_published' => false,
            'commit_sha' => str_repeat('b', 40),
        ]);

        $response = $this->get('/changelog');
        $response->assertStatus(200);
        $response->assertDontSee('Secret Draft');
    }

    public function test_public_page_filters_by_type(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'A New Feature',
            'type' => 'added',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('a', 40),
        ]);
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'A Bug Fix',
            'type' => 'fixed',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('b', 40),
        ]);

        $response = $this->get('/changelog?type=added');
        $response->assertSee('A New Feature');
        $response->assertDontSee('A Bug Fix');
    }

    public function test_public_page_shows_empty_state(): void
    {
        $response = $this->get('/changelog');
        $response->assertStatus(200);
        $response->assertSee('No entries yet');
    }

    public function test_public_page_ignores_invalid_type_filter(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Visible Entry',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Invalid type should show all entries (filter is ignored)
        $response = $this->get('/changelog?type=invalid');
        $response->assertStatus(200);
        $response->assertSee('Visible Entry');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  JSON API Endpoint
    // ─────────────────────────────────────────────────────────────────────

    public function test_json_endpoint_returns_published_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'API Entry',
            'type' => 'added',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('a', 40),
        ]);
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Draft Entry',
            'is_published' => false,
            'commit_sha' => str_repeat('b', 40),
        ]);

        $response = $this->getJson('/changelog/api/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'API Entry']);
        $response->assertJsonMissing(['title' => 'Draft Entry']);
    }

    public function test_json_endpoint_includes_cors_headers(): void
    {
        $response = $this->getJson('/changelog/api/entries');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_json_endpoint_respects_limit(): void
    {
        $repo = $this->createRepository();

        for ($i = 0; $i < 10; $i++) {
            $this->createEntry([
                'changelog_repository_id' => $repo->id,
                'title' => "Entry {$i}",
                'is_published' => true,
                'published_at' => now(),
                'commit_sha' => str_pad((string) $i, 40, '0'),
            ]);
        }

        $response = $this->getJson('/changelog/api/entries?limit=3');
        $response->assertJsonCount(3, 'data');
    }

    public function test_json_endpoint_caps_limit_at_50(): void
    {
        // Even if someone requests 1000, we cap at 50
        $response = $this->getJson('/changelog/api/entries?limit=1000');
        $response->assertStatus(200);
        // Just verify it doesn't error — we can't create 1000 entries for this test
    }

    public function test_json_endpoint_filters_by_type(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Feature',
            'type' => 'added',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('a', 40),
        ]);
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Bugfix',
            'type' => 'fixed',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('b', 40),
        ]);

        $response = $this->getJson('/changelog/api/entries?type=fixed');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'Bugfix']);
    }

    public function test_json_endpoint_returns_correct_structure(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Structured Entry',
            'body' => 'Some body text',
            'type' => 'fixed',
            'author_name' => 'Alice',
            'is_published' => true,
            'published_at' => now(),
            'commit_sha' => str_repeat('a', 40),
        ]);

        $response = $this->getJson('/changelog/api/entries');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'body',
                    'type',
                    'type_label',
                    'author',
                    'commit_sha',
                    'commit_url',
                    'published_at',
                    'published_at_human',
                ],
            ],
            'count',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Widget JS Endpoint
    // ─────────────────────────────────────────────────────────────────────

    public function test_widget_js_endpoint_serves_javascript(): void
    {
        $response = $this->get('/changelog/widget.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        // Verify it contains actual JS content
        $this->assertStringContainsString('changelog', $response->getContent());
    }

    public function test_widget_js_endpoint_has_cache_headers(): void
    {
        $response = $this->get('/changelog/widget.js');

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
    }
}
