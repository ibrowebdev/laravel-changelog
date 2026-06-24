<?php

namespace Ibrohim\Changelog\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Models\ChangelogRepository;

class ProcessGitHubPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * GitHub might send a burst of webhooks. If the DB is momentarily
     * unavailable, we want to retry — but not forever.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * Exponential-ish backoff: 10s, 30s, 60s.
     * Gives transient issues time to resolve without hammering the DB.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     *
     * @param  int    $repositoryId  The ID of the ChangelogRepository record.
     * @param  array  $commits       The raw commits array from the GitHub push payload.
     *
     * We accept the repository ID (not the model) and the commits array (not the
     * full payload) because:
     *   - The ID serialises cleanly to the queue without pulling in encrypted fields.
     *   - We only need the commits array, so we strip everything else at dispatch
     *     time to keep the queue payload small.
     */
    public function __construct(
        public readonly int $repositoryId,
        public readonly array $commits,
    ) {}

    /**
     * Execute the job.
     *
     * Iterates over each commit in the payload and creates a ChangelogEntry.
     * Uses updateOrCreate() keyed on [repository_id, commit_sha] to guarantee
     * idempotency — if GitHub retries the webhook, we update the existing entry
     * rather than creating a duplicate.
     *
     * Commits are processed individually (not as a bulk insert) because:
     *   - updateOrCreate() needs per-row uniqueness checks.
     *   - A push with 20 commits is rare; most pushes have 1-3 commits.
     *   - Individual inserts let us log per-commit results for debugging.
     */
    public function handle(): void
    {
        $repository = ChangelogRepository::find($this->repositoryId);

        // If the repo was deleted between dispatch and execution, bail out.
        // This is a race condition that can happen if someone removes the
        // repo from the dashboard while jobs are still in the queue.
        if (! $repository) {
            Log::warning("ProcessGitHubPushJob: Repository ID {$this->repositoryId} not found. Skipping.");
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->commits as $commit) {
            // ── Extract fields from the GitHub commit object ────────────
            //
            // GitHub's push payload commit structure:
            // {
            //   "id": "abc123...",          ← full 40-char SHA
            //   "message": "Fix login\n\nDetailed description...",
            //   "timestamp": "2026-06-23T12:00:00+00:00",
            //   "author": { "name": "John", "email": "john@example.com" },
            //   "url": "https://github.com/...",
            //   "added": [...], "removed": [...], "modified": [...]
            // }

            $sha = $commit['id'] ?? null;

            // Skip commits without a SHA — shouldn't happen, but defensive coding.
            if (! $sha) {
                $skipped++;
                continue;
            }

            $message = $commit['message'] ?? '';
            $author  = $commit['author'] ?? [];

            // ── Parse the commit message ────────────────────────────────
            //
            // Git commit messages follow a convention:
            //   Line 1: subject line (used as the title)
            //   Line 2: blank
            //   Line 3+: body
            //
            // We split on the first newline to separate subject from body.
            // The body may be empty if the developer only wrote a one-liner.
            $lines   = explode("\n", $message, 2);
            $title   = trim($lines[0]);
            $body    = isset($lines[1]) ? trim($lines[1]) : null;

            // Empty body string → null for cleaner DB storage
            if ($body === '') {
                $body = null;
            }

            // ── Parse the commit timestamp ──────────────────────────────
            //
            // GitHub provides ISO 8601 timestamps. Carbon::parse() handles
            // these natively. We wrap in try/catch because a malformed
            // timestamp shouldn't crash the entire job.
            $committedAt = null;
            if (! empty($commit['timestamp'])) {
                try {
                    $committedAt = Carbon::parse($commit['timestamp']);
                } catch (\Exception $e) {
                    Log::warning("ProcessGitHubPushJob: Could not parse timestamp for commit {$sha}: {$commit['timestamp']}");
                }
            }

            // ── Auto-detect the entry type from the commit message ──────
            //
            // This is a best-effort heuristic. Conventional Commits prefixes
            // like "fix:", "feat:", "chore:" map to changelog types. The product
            // owner can always override this in the dashboard.
            $type = $this->detectType($title);

            // ── AI Commit Rewrite ───────────────────────────────────────
            //
            // If laravel/ai is installed and a provider is configured, we pass the raw
            // commit message to our AI Agent to rewrite it into a user-friendly format.
            if (class_exists(\Laravel\Ai\AiServiceProvider::class) && config('changelog.ai.provider')) {
                try {
                    $response = \Ibrohim\Changelog\Agents\ChangelogAgent::make()->prompt(
                        prompt: $message,
                        provider: config('changelog.ai.provider'),
                        model: config('changelog.ai.model')
                    );
                    
                    // Strip potential Markdown JSON code blocks
                    $content = trim(str_replace(['```json', '```'], '', $response->text));
                    $aiData = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($aiData)) {
                        $title = $aiData['title'] ?? $title;
                        $body = $aiData['body'] ?? $body;
                        $type = $aiData['type'] ?? $type;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Changelog AI processing failed for commit {$sha}: " . $e->getMessage());
                }
            }

            // ── Create or update the entry ──────────────────────────────
            //
            // updateOrCreate() is keyed on [repository_id, commit_sha].
            // - First time: creates a new entry with all the fields.
            // - Retry/duplicate: updates the existing entry (harmless, same data).
            //
            // The unique constraint on the DB table is our safety net, but
            // updateOrCreate() avoids hitting that constraint with an exception.
            $entry = ChangelogEntry::updateOrCreate(
                [
                    'changelog_repository_id' => $repository->id,
                    'commit_sha' => $sha,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'original_commit_message' => $message,
                    'author_name' => $author['name'] ?? 'Unknown',
                    'author_email' => $author['email'] ?? 'unknown@example.com',
                    'type' => $type,
                    'committed_at' => $committedAt,
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        Log::info("ProcessGitHubPushJob: Processed push for {$repository->name}. Created: {$created}, Updated/Skipped: {$skipped}.");
    }

    /**
     * Detect the changelog entry type from the commit message subject line.
     *
     * Supports both Conventional Commits syntax ("feat: add login") and
     * common natural-language patterns ("Fix the broken navbar").
     *
     * Returns null if no pattern matches — the product owner can set the
     * type manually from the dashboard.
     */
    protected function detectType(string $title): ?string
    {
        $lowerTitle = strtolower($title);

        // Conventional Commits: "type(scope): description" or "type: description"
        // We match the prefix before the first colon.
        if (preg_match('/^(\w+)(\(.+\))?:\s/', $lowerTitle, $matches)) {
            $prefix = $matches[1];

            return match ($prefix) {
                'feat', 'feature' => 'added',
                'fix', 'bugfix'   => 'fixed',
                'change', 'refactor', 'perf', 'style' => 'changed',
                'remove', 'revert', 'deprecate' => 'removed',
                'security', 'vuln' => 'security',
                default => null,
            };
        }

        // Natural-language fallback: check if the subject starts with
        // common verbs. This is intentionally conservative — it's better
        // to return null (uncategorised) than to guess wrong.
        return match (true) {
            str_starts_with($lowerTitle, 'add '),
            str_starts_with($lowerTitle, 'added '),
            str_starts_with($lowerTitle, 'implement '),
            str_starts_with($lowerTitle, 'create ') => 'added',

            str_starts_with($lowerTitle, 'fix '),
            str_starts_with($lowerTitle, 'fixed '),
            str_starts_with($lowerTitle, 'resolve ') => 'fixed',

            str_starts_with($lowerTitle, 'change '),
            str_starts_with($lowerTitle, 'update '),
            str_starts_with($lowerTitle, 'refactor '),
            str_starts_with($lowerTitle, 'improve ') => 'changed',

            str_starts_with($lowerTitle, 'remove '),
            str_starts_with($lowerTitle, 'removed '),
            str_starts_with($lowerTitle, 'delete '),
            str_starts_with($lowerTitle, 'revert ') => 'removed',

            default => null,
        };
    }

    /**
     * Handle a job failure.
     *
     * Log the failure so the developer can investigate. This runs after
     * all retry attempts have been exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessGitHubPushJob: Failed for repository ID {$this->repositoryId}. Error: {$exception->getMessage()}");
    }
}
