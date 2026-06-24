<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Models\ChangelogRepository;
use Ibrohim\Changelog\Tests\TestCase;

class ModelTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────
    //  ChangelogRepository Tests
    // ─────────────────────────────────────────────────────────────────────

    public function test_repository_can_be_created(): void
    {
        $repo = $this->createRepository();

        $this->assertDatabaseHas('changelog_repositories', [
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'name' => 'test-owner/test-repo',
        ]);

        $this->assertInstanceOf(ChangelogRepository::class, $repo);
    }

    public function test_webhook_secret_is_encrypted_at_rest(): void
    {
        $repo = $this->createRepository(['webhook_secret' => 'my-super-secret']);

        // The decrypted value should match what we set
        $this->assertEquals('my-super-secret', $repo->webhook_secret);

        // The raw DB value should NOT be the plain text
        $raw = \DB::table('changelog_repositories')
            ->where('id', $repo->id)
            ->value('webhook_secret');

        $this->assertNotEquals('my-super-secret', $raw);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $repo = $this->createRepository(['is_active' => true]);
        $this->assertIsBool($repo->is_active);
        $this->assertTrue($repo->is_active);
    }

    public function test_find_by_owner_and_repo(): void
    {
        $this->createRepository();

        $found = ChangelogRepository::findByOwnerAndRepo('test-owner', 'test-repo');
        $this->assertNotNull($found);
        $this->assertEquals('test-owner', $found->owner);

        $notFound = ChangelogRepository::findByOwnerAndRepo('nonexistent', 'repo');
        $this->assertNull($notFound);
    }

    public function test_find_by_owner_and_repo_excludes_inactive(): void
    {
        $this->createRepository(['is_active' => false]);

        $found = ChangelogRepository::findByOwnerAndRepo('test-owner', 'test-repo');
        $this->assertNull($found);
    }

    public function test_active_scope(): void
    {
        $this->createRepository(['name' => 'active/repo', 'owner' => 'active', 'repo' => 'repo', 'github_id' => 1, 'is_active' => true]);
        $this->createRepository(['name' => 'inactive/repo', 'owner' => 'inactive', 'repo' => 'repo', 'github_id' => 2, 'is_active' => false]);

        $active = ChangelogRepository::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('active', $active->first()->owner);
    }

    public function test_repository_has_many_entries(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'commit_sha' => 'aaa111aaa111aaa111aaa111aaa111aaa111aaa1']);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'commit_sha' => 'bbb222bbb222bbb222bbb222bbb222bbb222bbb2']);

        $repo->refresh();
        $this->assertCount(2, $repo->entries);
    }

    public function test_github_url_accessor(): void
    {
        $repo = $this->createRepository(['owner' => 'acme', 'repo' => 'widgets']);
        $this->assertEquals('https://github.com/acme/widgets', $repo->github_url);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  ChangelogEntry Tests
    // ─────────────────────────────────────────────────────────────────────

    public function test_entry_can_be_created(): void
    {
        $entry = $this->createEntry();

        $this->assertDatabaseHas('changelog_entries', [
            'title' => 'Test entry',
        ]);

        $this->assertInstanceOf(ChangelogEntry::class, $entry);
    }

    public function test_entry_belongs_to_repository(): void
    {
        $repo = $this->createRepository();
        $entry = $this->createEntry(['changelog_repository_id' => $repo->id]);

        $this->assertEquals($repo->id, $entry->repository->id);
    }

    public function test_entry_publish_and_unpublish(): void
    {
        $entry = $this->createEntry(['is_published' => false]);

        $this->assertFalse($entry->is_published);
        $this->assertNull($entry->published_at);

        // Publish
        $entry->publish();
        $entry->refresh();

        $this->assertTrue($entry->is_published);
        $this->assertNotNull($entry->published_at);

        // Unpublish
        $entry->unpublish();
        $entry->refresh();

        $this->assertFalse($entry->is_published);
        $this->assertNull($entry->published_at);
    }

    public function test_published_scope(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);

        $published = ChangelogEntry::published()->get();
        $this->assertCount(1, $published);
        $this->assertTrue($published->first()->is_published);
    }

    public function test_draft_scope(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => true, 'published_at' => now(), 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'is_published' => false, 'commit_sha' => str_repeat('b', 40)]);

        $drafts = ChangelogEntry::draft()->get();
        $this->assertCount(1, $drafts);
        $this->assertFalse($drafts->first()->is_published);
    }

    public function test_of_type_scope(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id, 'type' => 'added', 'commit_sha' => str_repeat('a', 40)]);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'type' => 'fixed', 'commit_sha' => str_repeat('b', 40)]);

        $added = ChangelogEntry::ofType('added')->get();
        $this->assertCount(1, $added);
        $this->assertEquals('added', $added->first()->type);
    }

    public function test_short_sha_accessor(): void
    {
        $sha = 'abc123def456abc123def456abc123def456abc1';
        $entry = $this->createEntry(['commit_sha' => $sha]);

        $this->assertEquals('abc123d', $entry->short_sha);
    }

    public function test_type_label_accessor(): void
    {
        $entry = $this->createEntry(['type' => 'fixed']);
        $this->assertEquals('Fixed', $entry->type_label);

        $entry2 = $this->createEntry(['type' => null, 'commit_sha' => str_repeat('c', 40)]);
        $this->assertEquals('Uncategorised', $entry2->type_label);
    }

    public function test_commit_url_accessor(): void
    {
        $repo = $this->createRepository(['owner' => 'acme', 'repo' => 'widgets']);
        $sha = str_repeat('a', 40);
        $entry = $this->createEntry([
            'changelog_repository_id' => $repo->id,
            'commit_sha' => $sha,
        ]);

        // Load the relationship
        $entry->load('repository');

        $this->assertEquals("https://github.com/acme/widgets/commit/{$sha}", $entry->commit_url);
    }

    public function test_valid_types_returns_expected_array(): void
    {
        $types = ChangelogEntry::validTypes();

        $this->assertContains('added', $types);
        $this->assertContains('changed', $types);
        $this->assertContains('fixed', $types);
        $this->assertContains('removed', $types);
        $this->assertContains('security', $types);
        $this->assertCount(5, $types);
    }

    public function test_duplicate_commit_sha_per_repo_is_prevented(): void
    {
        $repo = $this->createRepository();
        $sha = str_repeat('a', 40);

        $this->createEntry(['changelog_repository_id' => $repo->id, 'commit_sha' => $sha]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createEntry(['changelog_repository_id' => $repo->id, 'commit_sha' => $sha]);
    }

    public function test_cascade_delete_removes_entries_when_repository_deleted(): void
    {
        $repo = $this->createRepository();
        $this->createEntry(['changelog_repository_id' => $repo->id]);

        $this->assertDatabaseCount('changelog_entries', 1);

        $repo->delete();

        $this->assertDatabaseCount('changelog_entries', 0);
    }
}
