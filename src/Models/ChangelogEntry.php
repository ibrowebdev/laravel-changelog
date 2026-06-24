<?php

namespace Ibrohim\Changelog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogEntry extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'changelog_entries';

    /**
     * Mass-assignable attributes.
     *
     * Every field that the ProcessGitHubPushJob or the dashboard edit form
     * might set is listed here. `original_commit_message` is fillable because
     * the job sets it once at creation time — it's never updated afterward.
     */
    protected $fillable = [
        'changelog_repository_id',
        'title',
        'body',
        'original_commit_message',
        'commit_sha',
        'author_name',
        'author_email',
        'type',
        'is_published',
        'published_at',
        'committed_at',
    ];

    /**
     * Attribute casting.
     *
     * - is_published: boolean for clean conditionals in Blade.
     * - published_at / committed_at: datetime so Carbon is available
     *   for formatting (e.g. $entry->published_at->diffForHumans()).
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * The repository this entry belongs to.
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(ChangelogRepository::class, 'changelog_repository_id');
    }

    // ──────────────────────────────────────────────
    //  Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope: only published entries, newest first.
     *
     * This is the primary query for the public changelog page.
     * The composite index on [is_published, published_at] keeps this fast.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->orderByDesc('published_at');
    }

    /**
     * Scope: only draft (unpublished) entries.
     *
     * Used on the dashboard to show entries awaiting curation.
     */
    public function scopeDraft($query)
    {
        return $query->where('is_published', false);
    }

    /**
     * Scope: filter by changelog type (added, changed, fixed, etc.)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ──────────────────────────────────────────────
    //  Actions
    // ──────────────────────────────────────────────

    /**
     * Publish this entry.
     *
     * Sets is_published to true and stamps published_at with the current time.
     * If the entry was previously published and unpublished, re-publishing
     * updates the published_at timestamp to "now" — this is intentional so
     * the public page reflects the most recent publication date.
     */
    public function publish(): self
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $this;
    }

    /**
     * Unpublish this entry.
     *
     * Removes it from the public changelog page. We clear published_at
     * so that re-publishing gives it a fresh timestamp.
     */
    public function unpublish(): self
    {
        $this->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        return $this;
    }

    // ──────────────────────────────────────────────
    //  Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the GitHub commit URL.
     *
     * Requires the repository relationship to be loaded.
     * Returns null if the relationship isn't available.
     */
    public function getCommitUrlAttribute(): ?string
    {
        if (! $this->repository) {
            return null;
        }

        return "{$this->repository->github_url}/commit/{$this->commit_sha}";
    }

    /**
     * Get the short (7-char) SHA for display purposes.
     */
    public function getShortShaAttribute(): string
    {
        return substr($this->commit_sha, 0, 7);
    }

    /**
     * Get the human-readable label for the entry type.
     *
     * Maps internal type slugs to capitalised display labels.
     * Returns "Uncategorised" for null types.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'added'    => 'Added',
            'changed'  => 'Changed',
            'fixed'    => 'Fixed',
            'removed'  => 'Removed',
            'security' => 'Security',
        ];

        return $labels[$this->type] ?? 'Uncategorised';
    }

    /**
     * Get the valid entry types.
     *
     * Centralised here so the dashboard form, validation rules, and
     * any future API can all reference the same list.
     */
    public static function validTypes(): array
    {
        return ['added', 'changed', 'fixed', 'removed', 'security'];
    }
}
