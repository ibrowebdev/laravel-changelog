# Laravel Changelog

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ibrohim/laravel-changelog.svg)](https://packagist.org/packages/ibrohim/laravel-changelog)
[![License](https://img.shields.io/packagist/l/ibrohim/laravel-changelog.svg)](https://packagist.org/packages/ibrohim/laravel-changelog)

A Laravel package that automatically syncs GitHub commits into editable changelog entries. It includes a dashboard for managing entries, a public changelog page, and an embeddable JavaScript widget.

## Features

- **GitHub Webhook Integration** — Automatically creates changelog entries from push events
- **HMAC Signature Verification** — Secure webhook validation using `X-Hub-Signature-256`
- **Smart Type Detection** — Auto-categorises commits from Conventional Commits and natural language
- **Admin Dashboard** — Filter, search, edit, publish/unpublish, and delete entries
- **Public Changelog Page** — Beautiful timeline-style layout grouped by date
- **Embeddable Widget** — Drop a `<script>` tag into any page for an instant changelog widget
- **JSON API** — Fetch published entries programmatically with CORS support
- **Facade API** — Clean programmatic interface via `Changelog::publishedEntries()`
- **Encrypted Secrets** — Webhook secrets are encrypted at rest using Laravel's `encrypted` cast
- **Fully Configurable** — Custom route prefix, middleware, pagination, and page text

## Requirements

- PHP 8.4 or 8.5
- Laravel 11, 12, or 13

## Installation

```bash
composer require ibrohim/laravel-changelog:@dev
```

The package uses Laravel's auto-discovery, so the service provider and facade are registered automatically.

Run the install command to publish the config, run migrations, and optionally register your first repository:

```bash
php artisan changelog:install
```

Or do it manually:

```bash
php artisan vendor:publish --tag=changelog-config
php artisan migrate
```

> **Note on Production:** If you are deploying to a production server where running interactive commands is difficult, you can skip registering a repository during install. Instead, simply visit the **Repositories Dashboard** at `/changelog/dashboard/repositories` in your browser to securely add your GitHub repository and webhook secret.

## Configuration

After publishing, the config file is at `config/changelog.php`:

```php
return [
    // URL prefix for all changelog routes
    'route_prefix' => 'changelog',

    // Middleware for the admin dashboard
    'dashboard_middleware' => ['web', 'auth'],

    // Public page settings
    'page_title' => 'Changelog',
    'page_subtitle' => 'New updates and improvements.',
    'meta_description' => 'See what\'s new — latest updates, fixes, and improvements.',

    // Entries per page on the public changelog
    'per_page' => 15,
];
```

### Customising Dashboard Access

To restrict the dashboard to specific users or roles:

```php
// config/changelog.php
'dashboard_middleware' => ['web', 'auth', 'can:manage-changelog'],
```

## Setting Up GitHub Webhooks

### 1. Register a Repository

You can register a repo via the install command or programmatically:

```php
use Ibrohim\Changelog\Facades\Changelog;

Changelog::addRepository(
    owner: 'your-org',
    repo: 'your-app',
    secret: 'your-webhook-secret',
    branch: 'main',
);
```

Or via Artisan:

```bash
php artisan changelog:install --repo=your-org/your-app --secret=your-webhook-secret
```

### 2. Configure the Webhook in GitHub

1. Go to your repository → **Settings** → **Webhooks** → **Add webhook**
2. Set the **Payload URL** to: `https://yourapp.com/changelog/webhook`
3. Set **Content type** to: `application/json`
4. Enter the **Secret** you used when registering the repository
5. Select **Just the push event**
6. Click **Add webhook**

### 3. Importing Historical Commits

If you've just installed the package and want to backfill your changelog with past commits, use the import command:

```bash
php artisan changelog:import owner/repo --limit=50
```

- `--limit=50`: The maximum number of historical commits to fetch (1-100 per request).
- `--branch=main`: The specific branch to fetch from (defaults to the repo's configured default branch).
- `--token=xxx`: Your GitHub Personal Access Token (recommended to avoid rate limits or for fetching from private repositories).

All imported commits will be processed and added to your dashboard as **draft** entries awaiting your review.

### 4. How It Works

```
GitHub push → POST /changelog/webhook
  → Middleware verifies HMAC signature
  → Controller checks event type & branch
  → Dispatches queued job
  → Job parses commits into ChangelogEntry records (as drafts)
  → Product owner curates & publishes via dashboard
  → Public page & widget show published entries
```

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/changelog` | GET | Public changelog page |
| `/changelog/widget.js` | GET | Public script for the embedded widget |
| `/changelog/webhook` | POST | Webhook endpoint (accepts GitHub push events) |
| `/changelog/dashboard` | GET | Admin dashboard (list entries) |
| `/changelog/dashboard/entries/{id}/edit` | GET | Edit entry form |
| `/changelog/dashboard/repositories` | GET | Manage connected GitHub repositories |
| `/changelog/dashboard/entries/{id}` | PUT | Update entry |
| `/changelog/dashboard/entries/{id}/publish` | POST | Publish entry |
| `/changelog/dashboard/entries/{id}/unpublish` | POST | Unpublish entry |
| `/changelog/dashboard/entries/{id}` | DELETE | Delete entry |
| `/changelog/api/entries` | GET | JSON API (for widget) |
| `/changelog/widget.js` | GET | Embeddable widget JS |

All routes are prefixed with the configured `route_prefix` (default: `changelog`).

## Embeddable Widget

Drop this snippet into any HTML page:

```html
<div id="changelog-widget"></div>
<script
  src="https://yourapp.com/changelog/widget.js"
  data-changelog-url="https://yourapp.com/changelog/api/entries"
  data-limit="5"
  data-theme="light"
></script>
```

### Widget Options

| Attribute | Default | Description |
|-----------|---------|-------------|
| `data-changelog-url` | **(required)** | Full URL to the JSON API endpoint |
| `data-container` | `#changelog-widget` | CSS selector for the mount element |
| `data-limit` | `5` | Number of entries to display |
| `data-type` | `""` | Filter by type: `added`, `changed`, `fixed`, `removed`, `security` |
| `data-theme` | `light` | `light` or `dark` |

The widget uses Shadow DOM for complete style encapsulation — it won't interfere with your page's CSS.

## Facade API

The `Changelog` facade provides a clean API for programmatic access:

```php
use Ibrohim\Changelog\Facades\Changelog;

// Get published entries
$entries = Changelog::publishedEntries();      // all
$entries = Changelog::publishedEntries(10);    // latest 10

// Get draft entries awaiting curation
$drafts = Changelog::draftEntries();

// Filter by type
$fixes = Changelog::entriesByType('fixed');

// Create a manual entry (not from a commit)
$entry = Changelog::createEntry([
    'title' => 'We just launched v2.0!',
    'body' => 'A complete redesign with new features.',
    'type' => 'added',
]);

// Publish / unpublish
Changelog::publish($entryId);
Changelog::unpublish($entryId);

// Pending count (useful for admin nav badges)
$count = Changelog::pendingCount();

// Repository management
$repo = Changelog::addRepository('owner', 'repo', 'secret');
$repo = Changelog::findRepository('owner', 'repo');
$all  = Changelog::repositories();
```

## Entry Types

Entries are categorised into these types:

| Type | Description | Auto-detected from |
|------|-------------|-------------------|
| `added` | New features | `feat:`, `Add ...`, `Implement ...` |
| `changed` | Changes to existing features | `refactor:`, `Update ...`, `Improve ...` |
| `fixed` | Bug fixes | `fix:`, `Fix ...`, `Resolve ...` |
| `removed` | Removed features | `remove:`, `Remove ...`, `Delete ...` |
| `security` | Security patches | `security:`, `vuln:` |

Type detection works with both [Conventional Commits](https://www.conventionalcommits.org/) syntax and natural language. Unrecognised messages are left as "Uncategorised" for the product owner to set manually.

## Customising Views

To customise the Blade templates:

```bash
php artisan vendor:publish --tag=changelog-views
```

This publishes views to `resources/views/vendor/changelog/`. You can modify:

- `dashboard/layout.blade.php` — Dashboard layout
- `dashboard/index.blade.php` — Entry list
- `dashboard/edit.blade.php` — Edit form
- `public/index.blade.php` — Public changelog page

## Testing

The package includes a comprehensive test suite using [Orchestra Testbench](https://github.com/orchestral/testbench):

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

### Test Coverage

- **Models** — Relationships, scopes, casts, accessors, constraints
- **Middleware** — Signature verification, missing headers, inactive repos
- **Webhook** — Event filtering, branch filtering, entry creation, idempotency
- **Job** — Commit parsing, type detection, edge cases, graceful failures
- **Dashboard** — CRUD operations, filtering, search, validation
- **Public Page** — Published vs draft, type filtering, empty state
- **JSON API** — Response structure, CORS, limits, filtering
- **Widget** — JS serving, content type, caching
- **Facade** — All manager methods

## Package Structure

```
laravel-changelog/
├── composer.json
├── phpunit.xml
├── config/
│   └── changelog.php
├── database/migrations/
│   ├── create_changelog_repositories_table.php
│   └── create_changelog_entries_table.php
├── resources/
│   ├── assets/
│   │   └── widget.js
│   └── views/
│       ├── dashboard/
│       │   ├── layout.blade.php
│       │   ├── index.blade.php
│       │   └── edit.blade.php
│       └── public/
│           └── index.blade.php
├── routes/
│   └── web.php
├── src/
│   ├── ChangelogManager.php
│   ├── ChangelogServiceProvider.php
│   ├── Console/
│   │   └── InstallCommand.php
│   ├── Facades/
│   │   └── Changelog.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── PublicChangelogController.php
│   │   │   ├── WebhookController.php
│   │   │   └── WidgetController.php
│   │   └── Middleware/
│   │       └── VerifyGitHubWebhook.php
│   ├── Jobs/
│   │   └── ProcessGitHubPushJob.php
│   └── Models/
│       ├── ChangelogEntry.php
│       └── ChangelogRepository.php
└── tests/
    ├── TestCase.php
    └── Feature/
        ├── ChangelogManagerTest.php
        ├── DashboardTest.php
        ├── ModelTest.php
        ├── ProcessGitHubPushJobTest.php
        ├── PublicChangelogTest.php
        └── WebhookTest.php
```

## Local Development

To develop this package within a Laravel application, add a path repository to the host app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-changelog"
        }
    ]
}
```

Then require the package:

```bash
composer require ibrohim/laravel-changelog:@dev
```

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
