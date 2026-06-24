<?php

namespace Ibrohim\Changelog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Ibrohim\Changelog\Models\ChangelogEntry;

class PublicChangelogController extends Controller
{
    /**
     * Display the public changelog page.
     *
     * Shows all published entries grouped by date, newest first.
     * Supports optional filtering by entry type via query parameter.
     *
     * This page is publicly accessible — no authentication required.
     * It's what end-users and customers see.
     */
    public function index(Request $request): View
    {
        $query = ChangelogEntry::published()->with('repository');

        // ── Optional type filter ────────────────────────────────────
        // The public page can be filtered by type, e.g. /changelog?type=fixed
        // This lets users see only bug fixes, new features, etc.
        if ($request->filled('type') && in_array($request->input('type'), ChangelogEntry::validTypes())) {
            $query->ofType($request->input('type'));
        }

        $entries = $query->paginate(
            config('changelog.per_page', 15)
        )->withQueryString();

        // Group entries by published date for the timeline-style layout.
        // We group by the formatted date string (e.g. "June 23, 2026")
        // so Blade can render date headers between entry groups.
        $grouped = $entries->getCollection()->groupBy(function ($entry) {
            return $entry->published_at->format('F j, Y');
        });

        return view('changelog::public.index', [
            'entries'     => $entries,
            'grouped'     => $grouped,
            'activeType'  => $request->input('type'),
            'types'       => ChangelogEntry::validTypes(),
        ]);
    }

    /**
     * Return published changelog entries as JSON.
     *
     * This endpoint powers the embeddable widget (Step 8).
     * It returns a lightweight JSON response with only the fields
     * the widget needs — no Blade rendering overhead.
     *
     * Supports CORS so the widget can be embedded on any domain.
     */
    public function json(Request $request): JsonResponse
    {
        $query = ChangelogEntry::published()->with('repository');

        if ($request->filled('type') && in_array($request->input('type'), ChangelogEntry::validTypes())) {
            $query->ofType($request->input('type'));
        }

        $limit = min((int) $request->input('limit', 10), 50);

        $entries = $query->limit($limit)->get()->map(function ($entry) {
            return [
                'id'           => $entry->id,
                'title'        => $entry->title,
                'body'         => $entry->body,
                'type'         => $entry->type,
                'type_label'   => $entry->type_label,
                'author'       => $entry->author_name,
                'commit_sha'   => $entry->short_sha,
                'commit_url'   => $entry->commit_url,
                'published_at' => $entry->published_at->toIso8601String(),
                'published_at_human' => $entry->published_at->diffForHumans(),
            ];
        });

        return response()->json([
            'data'  => $entries,
            'count' => $entries->count(),
        ])->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]);
    }
}
