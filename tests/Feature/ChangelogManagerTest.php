<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Facades\Changelog;
use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Models\ChangelogRepository;
use Ibrohim\Changelog\Tests\TestCase;

class ChangelogManagerTest extends TestCase
{
    public function test_facade_resolves_to_manager(): void
    {
        $this->assertInstanceOf(
            \Ibrohim\Changelog\ChangelogManager::class,
            Changelog::getFacadeRoot()
        );
    }

    public function test_add_repository(): void
    {
        $repo = Changelog::addRepository('acme', 'app', 'secret123', 'develop');

        $this->assertInstanceOf(ChangelogRepository::class, $repo);
        $this->assertEquals('acme/app', $repo->name);
        $this->assertEquals('acme', $repo->owner);
        $this->assertEquals('app', $repo->repo);
        $this->assertEquals('develop', $repo->default_branch);
        $this->assertEquals('secret123', $repo->webhook_secret);
        $this->assertTrue($repo->is_active);
    }

    public function test_find_repository(): void
    {
        $this->createRepository(['owner' => 'myorg', 'repo' => 'myapp']);

        $found = Changelog::findRepository('myorg', 'myapp');
        $this->assertNotNull($found);
        $this->assertEquals('myorg', $found->owner);

        $notFound = Changelog::findRepository('no', 'repo');
        $this->assertNull($notFound);
    }

    public function test_repositories_returns_all(): void
    {
        $this->createRepository(['name' => 'a/repo', 'owner' => 'a', 'github_id' => 1]);
        $this->createRepository(['name' => 'b/repo', 'owner' => 'b', 'github_id' => 2]);

        $repos = Changelog::repositories();
        $this->assertCount(2, $repos);
    }

    public function test_published_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);

        $published = Changelog::publishedEntries();
        $this->assertCount(1, $published);
    }

    public function test_published_entries_with_limit(): void
    {
        $repo = $this->createRepository();
        for ($i = 0; $i < 5; $i++) {
            $this->createEntry([
                'changelog_repository_id' => $repo->id,
                'is_published' => true,
                'published_at' => now(),
                'commit_sha' => str_pad((string) $i, 40, '0'),
            ]);
        }

        $limited = Changelog::publishedEntries(3);
        $this->assertCount(3, $limited);
    }

    public function test_draft_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);

        $drafts = Changelog::draftEntries();
        $this->assertCount(1, $drafts);
        $this->assertFalse($drafts->first()->is_published);
    }

    public function test_entries_by_type(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'type' => 'added', 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'type' => 'fixed', 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('b', 40)]);

        $added = Changelog::entriesByType('added');
        $this->assertCount(1, $added);
        $this->assertEquals('added', $added->first()->type);
    }

    public function test_create_manual_entry(): void
    {
        $repo = $this->createRepository();

        $entry = Changelog::createEntry([
            'changelog_repository_id' => $repo->id,
            'title' => 'Manual announcement',
            'body' => 'We just launched!',
            'type' => 'added',
        ]);

        $this->assertInstanceOf(ChangelogEntry::class, $entry);
        $this->assertEquals('Manual announcement', $entry->title);
        $this->assertStringStartsWith('manual-', $entry->commit_sha);
    }

    public function test_publish_via_facade(): void
    {
        $entry = $this->createEntry(['is_published' => false]);

        Changelog::publish($entry->id);

        $entry->refresh();
        $this->assertTrue($entry->is_published);
    }

    public function test_unpublish_via_facade(): void
    {
        $entry = $this->createEntry(['is_published' => true, 'published_at' => now()]);

        Changelog::unpublish($entry->id);

        $entry->refresh();
        $this->assertFalse($entry->is_published);
    }

    public function test_pending_count(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('c', 40)]);

        $this->assertEquals(2, Changelog::pendingCount());
    }

    public function test_valid_types(): void
    {
        $types = Changelog::validTypes();

        $this->assertIsArray($types);
        $this->assertCount(5, $types);
        $this->assertContains('added', $types);
    }
}
