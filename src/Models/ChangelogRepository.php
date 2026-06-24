<?php

namespace Ibrohim\Changelog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangelogRepository extends Model
{
    /**
     * The table associated with the model.
     *
     * Explicit because Laravel's auto-guessing would look for "changelog_repositories"
     * based on the class name — which happens to match here, but being explicit in a
     * package avoids surprises if someone extends or renames the model.
     */
    protected $table = 'changelog_repositories';

    /**
     * Mass-assignable attributes.
     *
     * We guard nothing extra beyond what $fillable permits. Every field that the
     * install command, admin panel, or internal code might set is listed here.
     */
    protected $fillable = [
        'name',
        'github_id',
        'owner',
        'repo',
        'default_branch',
        'webhook_secret',
        'is_active',
    ];

    /**
     * Attribute casting.
     *
     * - is_active: cast to boolean so blade `@if($repo->is_active)` works cleanly.
     * - webhook_secret: encrypted at rest using Laravel's built-in `encrypted` cast.
     *   This means the value is automatically encrypted when saved and decrypted when
     *   accessed — no manual Crypt::encrypt() calls needed. The migration stores it
     *   as `text` to accommodate the longer encrypted ciphertext.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'webhook_secret' => 'encrypted',
            'github_id' => 'integer',
        ];
    }

    /**
     * A repository has many changelog entries.
     *
     * This is the primary relationship — each push event creates one or more
     * entries under the repository it belongs to.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ChangelogEntry::class);
    }

    /**
     * Scope: only active repositories.
     *
     * Used in the webhook controller to skip disabled repos without
     * hitting an if-statement in the controller body.
     *
     * Usage: ChangelogRepository::active()->where('owner', $owner)->first()
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a repository by its owner/repo slug combo.
     *
     * This is the primary lookup method used by the webhook controller.
     * The GitHub push payload provides `repository.owner.login` and
     * `repository.name` separately, so this matches on both.
     */
    public static function findByOwnerAndRepo(string $owner, string $repo): ?self
    {
        return static::active()
            ->where('owner', $owner)
            ->where('repo', $repo)
            ->first();
    }

    /**
     * Get the full GitHub URL for this repository.
     */
    public function getGithubUrlAttribute(): string
    {
        return "https://github.com/{$this->owner}/{$this->repo}";
    }
}
