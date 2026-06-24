<?php

namespace Ibrohim\Changelog;

use Illuminate\Support\ServiceProvider;
use Ibrohim\Changelog\Console\InstallCommand;

class ChangelogServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     *
     * This method runs during the "register" phase of the Laravel boot cycle.
     * Only bind things into the container here — don't load routes, views, etc.
     * That goes in boot().
     */
    public function register(): void
    {
        // ── Merge config ────────────────────────────────────────────────
        //
        // mergeConfigFrom() makes the package config available immediately
        // via config('changelog.key') WITHOUT requiring the user to publish
        // the config file. If they do publish it, their values take precedence
        // over the package defaults (that's what "merge" means).
        $this->mergeConfigFrom(
            __DIR__ . '/../config/changelog.php',
            'changelog'
        );

        // ── Register the ChangelogManager as a singleton ────────────────
        //
        // The manager is the central service the Facade points to.
        // Registered as a singleton so the same instance is reused throughout
        // the request lifecycle.
        $this->app->singleton('changelog', function ($app) {
            return new ChangelogManager($app);
        });
    }

    /**
     * Bootstrap package services.
     *
     * This method runs during the "boot" phase — all service providers have
     * been registered at this point, so it's safe to load routes, views,
     * migrations, etc.
     */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerPublishing();
    }

    /**
     * Load the package routes.
     *
     * Routes are always loaded (not just when running in console).
     * The route file handles its own middleware groups and prefixes.
     */
    protected function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register the package views under the "changelog" namespace.
     *
     * This allows views to be referenced as 'changelog::dashboard.index'.
     * If the user publishes views, their copies in resources/views/vendor/changelog
     * take precedence — so they can customise templates without modifying
     * the package source.
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'changelog');
    }

    /**
     * Load the package migrations.
     *
     * Migrations are loaded automatically — the user does NOT need to publish
     * them. Running `php artisan migrate` will pick them up. If the user
     * wants to customise the migrations, they can publish them.
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register the webhook verification middleware alias.
     *
     * This allows the host app to reference the middleware as
     * 'changelog.verify-webhook' in their own route definitions
     * if they want to add custom webhook endpoints.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(\Illuminate\Routing\Router::class);
        $router->aliasMiddleware(
            'changelog.verify-webhook',
            \Ibrohim\Changelog\Http\Middleware\VerifyGitHubWebhook::class
        );
    }

    /**
     * Register Artisan commands.
     *
     * Commands are only registered when running in the console (CLI).
     * No point loading them for HTTP requests.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    /**
     * Register publishable assets.
     *
     * Publishing is only available when running in the console.
     * The user can publish individual groups:
     *
     *   php artisan vendor:publish --tag=changelog-config
     *   php artisan vendor:publish --tag=changelog-views
     *   php artisan vendor:publish --tag=changelog-migrations
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Config file
            $this->publishes([
                __DIR__ . '/../config/changelog.php' => config_path('changelog.php'),
            ], 'changelog-config');

            // Blade views (for customisation)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/changelog'),
            ], 'changelog-views');

            // Migrations (for customisation)
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'changelog-migrations');
        }
    }
}
