<?php

namespace Ibrohim\Changelog;

use Illuminate\Contracts\Foundation\Application;
use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Models\ChangelogRepository;

class ChangelogManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * Create a new ChangelogManager instance.
     *
     * The manager is the "brain" of the package — the single service that
     * the Facade delegates to. It provides a clean programmatic API for
     * the host app to interact with the changelog system without directly
     * touching Eloquent models.
     *
     * Usage via Facade:
     *   Changelog::publishedEntries()
     *   Changelog::addRepository('owner', 'repo', 'secret')
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Repository Methods
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Register a new GitHub repository for changelog tracking.
     *
     * This is the programmatic alternative to manually inserting a DB record.
     * The install command and any future admin UI use this method.
     *
     * @param  string  $owner   GitHub owner/org (e.g. "ibrohim")
     * @param  string  $repo    Repository name (e.g. "laravel-changelog")
     * @param  string  $secret  The webhook secret for HMAC verification
     * @param  string  $branch  The branch to track (default: "main")
     * @return ChangelogRepository
     */
    public function addRepository(
        string $owner,
        string $repo,
        string $secret,
        string $branch = 'main',
    ): ChangelogRepository {
        return ChangelogRepository::create([
            'name' => "{$owner}/{$repo}",
            'github_id' => 0, // Will be updated on first webhook delivery
            'owner' => $owner,
            'repo' => $repo,
            'default_branch' => $branch,
            'webhook_secret' => $secret,
            'is_active' => true,
        ]);
    }

    /**
     * Find a repository by its owner and repo name.
     */
    public function findRepository(string $owner, string $repo): ?ChangelogRepository
    {
        return ChangelogRepository::findByOwnerAndRepo($owner, $repo);
    }

    /**
     * Get all registered repositories.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ChangelogRepository>
     */
    public function repositories()
    {
        return ChangelogRepository::orderBy('name')->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Entry Methods
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get all published entries, newest first.
     *
     * This is the primary query for the public changelog.
     * The Facade makes this available as: Changelog::publishedEntries()
     *
     * @param  int|null  $limit  Max entries to return. Null = all.
     * @return \Illuminate\Database\Eloquent\Collection<ChangelogEntry>
     */
    public function publishedEntries(?int $limit = null)
    {
        $query = ChangelogEntry::published()->with('repository');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get all draft (unpublished) entries awaiting curation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ChangelogEntry>
     */
    public function draftEntries()
    {
        return ChangelogEntry::draft()
            ->with('repository')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get published entries filtered by type.
     *
     * @param  string  $type  One of: added, changed, fixed, removed, security
     * @return \Illuminate\Database\Eloquent\Collection<ChangelogEntry>
     */
    public function entriesByType(string $type)
    {
        return ChangelogEntry::published()
            ->ofType($type)
            ->with('repository')
            ->get();
    }

    /**
     * Create a changelog entry manually (not from a webhook).
     *
     * Useful for adding entries that don't correspond to Git commits,
     * e.g. product announcements, policy changes, etc.
     *
     * @param  array  $attributes  Must include 'title'. Optional: body, type, changelog_repository_id
     * @return ChangelogEntry
     */
    public function createEntry(array $attributes): ChangelogEntry
    {
        return ChangelogEntry::create(array_merge([
            'original_commit_message' => $attributes['title'] ?? '',
            'commit_sha' => 'manual-' . bin2hex(random_bytes(16)),
            'author_name' => $attributes['author_name'] ?? 'Manual Entry',
            'author_email' => $attributes['author_email'] ?? 'manual@changelog',
        ], $attributes));
    }

    /**
     * Publish an entry by ID.
     */
    public function publish(int $entryId): ChangelogEntry
    {
        $entry = ChangelogEntry::findOrFail($entryId);
        $entry->publish();

        return $entry;
    }

    /**
     * Unpublish an entry by ID.
     */
    public function unpublish(int $entryId): ChangelogEntry
    {
        $entry = ChangelogEntry::findOrFail($entryId);
        $entry->unpublish();

        return $entry;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Utility Methods
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get the count of pending (draft) entries.
     *
     * Useful for showing a badge in the host app's admin nav.
     */
    public function pendingCount(): int
    {
        return ChangelogEntry::draft()->count();
    }

    /**
     * Get the valid entry types.
     */
    public function validTypes(): array
    {
        return ChangelogEntry::validTypes();
    }
}
