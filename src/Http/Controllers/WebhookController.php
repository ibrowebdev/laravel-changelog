<?php

namespace Ibrohim\Changelog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ibrohim\Changelog\Jobs\ProcessGitHubPushJob;
use Ibrohim\Changelog\Models\ChangelogRepository;

class WebhookController extends Controller
{
    /**
     * Handle an incoming GitHub webhook delivery.
     *
     * By the time this method runs, the VerifyGitHubWebhook middleware has
     * already:
     *   1. Verified the HMAC signature (so we know it's genuinely from GitHub).
     *   2. Looked up the ChangelogRepository and stashed it on the request.
     *
     * This controller's only job is to:
     *   - Check the event type (we only care about "push" events).
     *   - Check the branch (we only process the repo's default branch).
     *   - Dispatch a queued job with the payload for async processing.
     *   - Return a fast 200 response so GitHub doesn't time out.
     *
     * Why dispatch a job instead of processing inline?
     *   - GitHub expects a response within 10 seconds or it marks the delivery
     *     as failed (and will retry, causing duplicates).
     *   - A push event can contain up to 20 commits; parsing and inserting them
     *     takes non-trivial time, especially with DB writes.
     *   - Queued jobs automatically get retries, backoff, and failure handling
     *     via Laravel's queue system.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // ── 1. Retrieve the repository (set by the middleware) ──────────
        /** @var ChangelogRepository $repository */
        $repository = $request->attributes->get('changelog_repository');

        // ── 2. Only process "push" events ───────────────────────────────
        //
        // GitHub sends many event types to a webhook endpoint: push, pull_request,
        // issues, release, etc. We only care about push. The X-GitHub-Event header
        // tells us which event type this is.
        //
        // We return 200 (not 4xx) for non-push events because from GitHub's
        // perspective the delivery was successful — we just chose not to act on it.
        // Returning 4xx would cause GitHub to flag the webhook as unhealthy.
        $event = $request->header('X-GitHub-Event');

        if ($event !== 'push') {
            return response()->json([
                'message' => "Event '{$event}' ignored. Only 'push' events are processed.",
            ], 200);
        }

        // ── 3. Check the branch ─────────────────────────────────────────
        //
        // The payload's `ref` field is a full Git ref like "refs/heads/main".
        // We extract the branch name and compare it to the repo's configured
        // default_branch. This prevents feature-branch commits from creating
        // changelog noise.
        $ref = $request->input('ref', '');
        $branch = str_replace('refs/heads/', '', $ref);

        if ($branch !== $repository->default_branch) {
            return response()->json([
                'message' => "Branch '{$branch}' ignored. Only '{$repository->default_branch}' is tracked.",
            ], 200);
        }

        // ── 4. Check for commits ────────────────────────────────────────
        //
        // A push event with zero commits can happen (e.g. force-push that
        // rewrites history but adds nothing new, or a tag push). Skip these.
        $commits = $request->input('commits', []);

        if (empty($commits)) {
            return response()->json([
                'message' => 'No commits in payload.',
            ], 200);
        }

        // ── 5. Dispatch the processing job ──────────────────────────────
        //
        // We pass the repository ID and the raw commits array. We intentionally
        // pass the ID (not the model) so the job serialises cleanly — Laravel
        // will re-fetch the model from the DB when the job runs, ensuring fresh
        // data.
        ProcessGitHubPushJob::dispatch($repository->id, $commits);

        return response()->json([
            'message' => 'Webhook received. Processing ' . count($commits) . ' commit(s).',
        ], 200);
    }
}
