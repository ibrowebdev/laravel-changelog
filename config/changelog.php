<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for all changelog routes. Change this if "/changelog"
    | conflicts with an existing route in your application.
    |
    | Webhook:   POST /changelog/webhook
    | Dashboard: GET  /changelog/dashboard
    | Public:    GET  /changelog
    | Widget JS: GET  /changelog/widget.js
    | API:       GET  /changelog/api/entries
    |
    */

    'route_prefix' => 'changelog',

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the dashboard routes. By default, the dashboard
    | requires authentication via the 'auth' middleware. You can add
    | additional middleware for role-based access control.
    |
    | Examples:
    |   ['web', 'auth']                       — any authenticated user
    |   ['web', 'auth', 'can:manage-changelog'] — policy-based
    |   ['web', 'auth', 'admin']              — custom middleware
    |
    */

    'dashboard_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Public Page Settings
    |--------------------------------------------------------------------------
    |
    | Customise the public-facing changelog page text and SEO metadata.
    |
    */

    'page_title' => 'Changelog',

    'page_subtitle' => 'New updates and improvements. Follow along to see what\'s changed.',

    'meta_description' => 'See what\'s new — latest updates, fixes, and improvements.',

    /*
    |--------------------------------------------------------------------------
    | Entries Per Page
    |--------------------------------------------------------------------------
    |
    | How many entries to show per page on the public changelog. The dashboard
    | always shows 20 per page.
    |
    */

    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | AI Integration (laravel/ai)
    |--------------------------------------------------------------------------
    |
    | The package uses the official Laravel AI SDK to automatically translate
    | raw git commit messages into customer-friendly changelog entries.
    | Set your preferred provider and model below.
    |
    */

    'ai' => [
        'provider' => env('CHANGELOG_AI_PROVIDER', 'openai'),
        'model' => env('CHANGELOG_AI_MODEL', 'gpt-4o-mini'),
    ],

];
