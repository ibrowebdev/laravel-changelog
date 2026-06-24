<?php

use Illuminate\Support\Facades\Route;
use Ibrohim\Changelog\Http\Controllers\DashboardController;
use Ibrohim\Changelog\Http\Controllers\PublicChangelogController;
use Ibrohim\Changelog\Http\Controllers\WebhookController;
use Ibrohim\Changelog\Http\Controllers\WidgetController;

/*
|--------------------------------------------------------------------------
| Changelog Package Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the ChangelogServiceProvider. They are split
| into three groups:
|
| 1. Webhook — receives GitHub push events (no CSRF, has signature verification)
| 2. Dashboard — admin area for managing changelog entries (auth + CSRF)
| 3. Public — the public-facing changelog page (no auth)
|
| The route prefix is configurable via config('changelog.route_prefix').
|
*/

$prefix = config('changelog.route_prefix', 'changelog');
$dashboardMiddleware = config('changelog.dashboard_middleware', ['web', 'auth']);

// ── Webhook Route ────────────────────────────────────────────────────────
//
// POST /changelog/webhook
//
// Excluded from CSRF — GitHub can't send a CSRF token.
// The VerifyGitHubWebhook middleware handles authentication via HMAC.
Route::post("{$prefix}/webhook", WebhookController::class)
    ->middleware([
        'api',
        \Ibrohim\Changelog\Http\Middleware\VerifyGitHubWebhook::class,
    ])
    ->name('changelog.webhook');

// ── Dashboard Routes ─────────────────────────────────────────────────────
//
// Protected by whatever middleware the host app configures (defaults to
// ['web', 'auth']). The host app can override this in config/changelog.php
// to add role-based middleware like 'can:manage-changelog' or 'admin'.
Route::prefix("{$prefix}/dashboard")
    ->middleware($dashboardMiddleware)
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->name('changelog.dashboard.index');

        Route::get('/entries/{id}/edit', [DashboardController::class, 'edit'])
            ->name('changelog.dashboard.edit');

        Route::put('/entries/{id}', [DashboardController::class, 'update'])
            ->name('changelog.dashboard.update');

        Route::post('/entries/{id}/publish', [DashboardController::class, 'publish'])
            ->name('changelog.dashboard.publish');

        Route::post('/entries/{id}/unpublish', [DashboardController::class, 'unpublish'])
            ->name('changelog.dashboard.unpublish');

        Route::delete('/entries/{id}', [DashboardController::class, 'destroy'])
            ->name('changelog.dashboard.destroy');

        // Repository Management
        Route::get('/repositories', [DashboardController::class, 'repositories'])
            ->name('changelog.dashboard.repositories');

        Route::post('/repositories', [DashboardController::class, 'storeRepository'])
            ->name('changelog.dashboard.store-repository');

        Route::delete('/repositories/{id}', [DashboardController::class, 'destroyRepository'])
            ->name('changelog.dashboard.destroy-repository');
    });

// ── Public Routes ────────────────────────────────────────────────────────
//
// No authentication — these are meant for end-users and customers.
// The 'web' middleware group is applied for session/cookie support
// (needed for pagination, etc.) but no 'auth' middleware.
Route::middleware('web')->group(function () use ($prefix) {
    // HTML page — the public changelog
    Route::get("{$prefix}", [PublicChangelogController::class, 'index'])
        ->name('changelog.public.index');

    // JSON endpoint — powers the embeddable widget (Step 8)
    // Returns published entries as JSON with CORS headers.
    Route::get("{$prefix}/api/entries", [PublicChangelogController::class, 'json'])
        ->name('changelog.api.entries');
});

// ── Widget Route ─────────────────────────────────────────────────────────
//
// Serves the embeddable widget JS file. No middleware needed — this is a
// static asset served with cache headers. Keeping it outside any middleware
// group makes it as fast as possible.
Route::get("{$prefix}/widget.js", WidgetController::class)
    ->name('changelog.widget.js');
