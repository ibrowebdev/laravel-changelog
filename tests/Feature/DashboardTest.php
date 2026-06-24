<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Tests\TestCase;

class DashboardTest extends TestCase
{
    /**
     * Override environment to disable auth middleware for dashboard tests.
     *
     * In a real app the dashboard is behind auth. For testing purposes
     * we use 'web' only so we don't need to fake authentication.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('changelog.dashboard_middleware', ['web']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Index
    // ─────────────────────────────────────────────────────────────────────

    public function test_dashboard_index_loads(): void
    {
        $response = $this->get('/changelog/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Changelog Entries');
    }

    public function test_dashboard_index_shows_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'My Test Feature',
        ]);

        $response = $this->get('/changelog/dashboard');
        $response->assertStatus(200);
        $response->assertSee('My Test Feature');
    }

    public function test_dashboard_index_shows_empty_state(): void
    {
        $response = $this->get('/changelog/dashboard');
        $response->assertStatus(200);
        $response->assertSee('No entries found');
    }

    public function test_dashboard_filters_by_status(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'Published Entry', 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'Draft Entry', 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);

        $response = $this->get('/changelog/dashboard?status=published');
        $response->assertSee('Published Entry');
        $response->assertDontSee('Draft Entry');

        $response = $this->get('/changelog/dashboard?status=draft');
        $response->assertSee('Draft Entry');
        $response->assertDontSee('Published Entry');
    }

    public function test_dashboard_filters_by_type(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'A Feature', 'type' => 'added', 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'A Bugfix', 'type' => 'fixed', 'commit_sha' => str_repeat('b', 40)]);

        $response = $this->get('/changelog/dashboard?type=added');
        $response->assertSee('A Feature');
        $response->assertDontSee('A Bugfix');
    }

    public function test_dashboard_search(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'Dark mode implementation', 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'title' => 'Fix login bug', 'commit_sha' => str_repeat('b', 40)]);

        $response = $this->get('/changelog/dashboard?search=dark+mode');
        $response->assertSee('Dark mode implementation');
        $response->assertDontSee('Fix login bug');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Edit
    // ─────────────────────────────────────────────────────────────────────

    public function test_edit_page_loads(): void
    {
        $entry = $this->createEntry(['title' => 'Editable Entry']);

        $response = $this->get("/changelog/dashboard/entries/{$entry->id}/edit");
        $response->assertStatus(200);
        $response->assertSee('Editable Entry');
        $response->assertSee('Original Commit Message');
    }

    public function test_edit_page_returns_404_for_invalid_id(): void
    {
        $response = $this->get('/changelog/dashboard/entries/99999/edit');
        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Update
    // ─────────────────────────────────────────────────────────────────────

    public function test_entry_can_be_updated(): void
    {
        $entry = $this->createEntry(['title' => 'Old Title', 'type' => 'added']);

        $response = $this->put("/changelog/dashboard/entries/{$entry->id}", [
            'title' => 'New Title',
            'body' => 'Updated description',
            'type' => 'fixed',
        ]);

        $response->assertRedirect();

        $entry->refresh();
        $this->assertEquals('New Title', $entry->title);
        $this->assertEquals('Updated description', $entry->body);
        $this->assertEquals('fixed', $entry->type);
    }

    public function test_update_validates_title_is_required(): void
    {
        $entry = $this->createEntry();

        $response = $this->put("/changelog/dashboard/entries/{$entry->id}", [
            'title' => '',
            'body' => 'Some body',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_update_validates_type_must_be_valid(): void
    {
        $entry = $this->createEntry();

        $response = $this->put("/changelog/dashboard/entries/{$entry->id}", [
            'title' => 'Valid title',
            'type' => 'invalid-type',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_update_allows_nullable_type(): void
    {
        $entry = $this->createEntry(['type' => 'added']);

        $response = $this->put("/changelog/dashboard/entries/{$entry->id}", [
            'title' => 'Title',
            'type' => '',
        ]);

        $response->assertRedirect();

        $entry->refresh();
        $this->assertNull($entry->type);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Publish / Unpublish
    // ─────────────────────────────────────────────────────────────────────

    public function test_entry_can_be_published(): void
    {
        $entry = $this->createEntry(['is_published' => false]);

        $response = $this->post("/changelog/dashboard/entries/{$entry->id}/publish");
        $response->assertRedirect();

        $entry->refresh();
        $this->assertTrue($entry->is_published);
        $this->assertNotNull($entry->published_at);
    }

    public function test_entry_can_be_unpublished(): void
    {
        $entry = $this->createEntry(['is_published' => true, 'published_at' => now()]);

        $response = $this->post("/changelog/dashboard/entries/{$entry->id}/unpublish");
        $response->assertRedirect();

        $entry->refresh();
        $this->assertFalse($entry->is_published);
        $this->assertNull($entry->published_at);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Delete
    // ─────────────────────────────────────────────────────────────────────

    public function test_entry_can_be_deleted(): void
    {
        $entry = $this->createEntry();

        $response = $this->delete("/changelog/dashboard/entries/{$entry->id}");
        $response->assertRedirect(route('changelog.dashboard.index'));

        $this->assertDatabaseMissing('changelog_entries', ['id' => $entry->id]);
    }
}
