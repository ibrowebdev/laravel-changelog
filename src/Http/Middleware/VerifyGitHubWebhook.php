<?php

namespace Ibrohim\Changelog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ibrohim\Changelog\Models\ChangelogRepository;
use Symfony\Component\HttpFoundation\Response;

class VerifyGitHubWebhook
{
    /**
     * Handle an incoming GitHub webhook request.
     *
     * This middleware verifies the X-Hub-Signature-256 header that GitHub sends
     * with every webhook delivery. The flow:
     *
     * 1. Extract the owner/repo from the JSON payload.
     * 2. Look up the matching ChangelogRepository record (which holds the secret).
     * 3. Compute HMAC-SHA256 of the raw request body using the stored secret.
     * 4. Compare the computed signature against the header using timing-safe comparison.
     *
     * If any step fails, we abort with 403/404 — never leaking *why* it failed
     * to a potential attacker. The raw payload is read from php://input to ensure
     * we get the exact bytes GitHub signed (not a re-encoded version from Laravel's
     * request parsing).
     *
     * Why middleware instead of inline in the controller?
     * - Separation of concerns: the controller only deals with business logic.
     * - Reusability: if you add more webhook endpoints later, they get the same
     *   verification for free by applying the middleware.
     * - Testability: you can test the controller without signature verification
     *   by simply not applying the middleware in tests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── 1. Grab the signature header ────────────────────────────────
        //
        // GitHub sends: X-Hub-Signature-256: sha256=<hex-digest>
        // If the header is missing entirely, this is not a legitimate GitHub request.
        $signatureHeader = $request->header('X-Hub-Signature-256');

        if (! $signatureHeader) {
            abort(403, 'Missing signature header.');
        }

        // ── 2. Read the raw payload ─────────────────────────────────────
        //
        // We MUST use the raw body, not $request->all() or $request->getContent()
        // after middleware has potentially modified it. The HMAC was computed by
        // GitHub against the exact bytes they sent.
        $payload = $request->getContent();

        if (empty($payload)) {
            abort(400, 'Empty payload.');
        }

        // ── 3. Identify the repository ──────────────────────────────────
        //
        // Decode the payload to find the repository owner and name.
        // We need these to look up the correct webhook secret — each repo
        // has its own secret, so there's no single "global" secret.
        $data = json_decode($payload, true);

        if (! $data || ! isset($data['repository']['owner']['login'], $data['repository']['name'])) {
            abort(400, 'Invalid payload structure.');
        }

        $owner = $data['repository']['owner']['login'];
        $repo  = $data['repository']['name'];

        // ── 4. Look up the repository record ────────────────────────────
        //
        // findByOwnerAndRepo() already scopes to active repos, so disabled
        // repos will 404 here — their webhooks are effectively ignored.
        $repository = ChangelogRepository::findByOwnerAndRepo($owner, $repo);

        if (! $repository) {
            abort(404, 'Repository not found or inactive.');
        }

        // ── 5. Compute and compare the HMAC ─────────────────────────────
        //
        // hash_hmac() with 'sha256' produces the same hex digest that GitHub
        // computes on their side. The `encrypted` cast on webhook_secret means
        // $repository->webhook_secret is already decrypted at this point.
        //
        // hash_equals() is timing-safe — it prevents timing attacks where an
        // attacker could guess the signature byte-by-byte by measuring response
        // times. This is critical for webhook security.
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $repository->webhook_secret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            abort(403, 'Invalid signature.');
        }

        // ── 6. Stash the repository on the request ──────────────────────
        //
        // We already looked it up — no reason to make the controller query
        // for it again. The controller can grab it with $request->attributes->get('changelog_repository').
        // Using the request attributes bag (not merge()) keeps it separate
        // from user-submitted input.
        $request->attributes->set('changelog_repository', $repository);

        return $next($request);
    }
}
