<?php

namespace Ibrohim\Changelog\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class WidgetController extends Controller
{
    /**
     * Serve the embeddable widget JavaScript file.
     *
     * Why a controller instead of serving a static file?
     *
     * 1. The package's resources/ directory isn't publicly accessible in the
     *    host app unless assets are published. A controller ensures the widget
     *    is always available at a predictable URL without requiring any publish step.
     *
     * 2. We can set proper cache headers (1 hour) so the browser caches the file.
     *    After the first load, subsequent requests are served from the browser cache.
     *
     * 3. We can set the correct Content-Type and CORS headers.
     *
     * The JS file is cached in Laravel's cache for 1 hour to avoid reading
     * from disk on every request. In production with OPcache, this is nearly free.
     */
    public function __invoke(): Response
    {
        $js = Cache::remember('changelog_widget_js', 3600, function () {
            return file_get_contents(__DIR__ . '/../../../resources/assets/widget.js');
        });

        return response($js, 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
