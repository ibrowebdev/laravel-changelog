<?php

namespace Ibrohim\Changelog\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Ibrohim\Changelog\Models\ChangelogEntry;
use Ibrohim\Changelog\Models\ChangelogRepository;

class DashboardController extends Controller
{
    /**
     * Display the changelog dashboard — a filterable list of all entries.
     *
     * Supports filtering by:
     *   - status: "published", "draft", or "all" (default)
     *   - type: "added", "changed", "fixed", etc.
     *   - repository: filter by a specific repository ID
     *   - search: free-text search on title and original commit message
     *
     * Results are paginated at 20 entries per page and ordered newest-first.
     */
    public function index(Request $request): View
    {
        $query = ChangelogEntry::with('repository');

        // ── Status filter ───────────────────────────────────────────
        if ($request->filled('status')) {
            match ($request->input('status')) {
                'published' => $query->where('is_published', true),
                'draft'     => $query->where('is_published', false),
                default     => null, // "all" — no filter
            };
        }

        // ── Type filter ─────────────────────────────────────────────
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // ── Repository filter ───────────────────────────────────────
        if ($request->filled('repository')) {
            $query->where('changelog_repository_id', $request->input('repository'));
        }

        // ── Search ──────────────────────────────────────────────────
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('original_commit_message', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $repositories = ChangelogRepository::orderBy('name')->get();

        return view('changelog::dashboard.index', [
            'entries'      => $entries,
            'repositories' => $repositories,
            'filters'      => $request->only(['status', 'type', 'repository', 'search']),
        ]);
    }

    /**
     * Show the edit form for a single changelog entry.
     *
     * The product owner can:
     *   - Rewrite the title (which was initially the commit subject line)
     *   - Add or edit the body (rich-text expansion of the commit message)
     *   - Set or change the entry type (added, changed, fixed, etc.)
     *
     * The original commit message is displayed read-only for reference.
     */
    public function edit(int $id): View
    {
        $entry = ChangelogEntry::with('repository')->findOrFail($id);

        return view('changelog::dashboard.edit', [
            'entry' => $entry,
            'types' => ChangelogEntry::validTypes(),
        ]);
    }

    /**
     * Update a changelog entry.
     *
     * Only the curated fields (title, body, type) are updatable.
     * Fields like commit_sha, author_name, and original_commit_message
     * are set by the job and should never be edited by the product owner.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $entry = ChangelogEntry::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'nullable|string|max:10000',
            'type'  => 'nullable|string|in:' . implode(',', ChangelogEntry::validTypes()),
        ]);

        $entry->update($validated);

        return redirect()
            ->route('changelog.dashboard.edit', $entry->id)
            ->with('success', 'Entry updated successfully.');
    }

    /**
     * Publish a changelog entry (makes it visible on the public page).
     */
    public function publish(int $id): RedirectResponse
    {
        $entry = ChangelogEntry::findOrFail($id);
        $entry->publish();

        return redirect()
            ->back()
            ->with('success', "Entry \"{$entry->title}\" has been published.");
    }

    /**
     * Unpublish a changelog entry (removes it from the public page).
     */
    public function unpublish(int $id): RedirectResponse
    {
        $entry = ChangelogEntry::findOrFail($id);
        $entry->unpublish();

        return redirect()
            ->back()
            ->with('success', "Entry \"{$entry->title}\" has been unpublished.");
    }

    /**
     * Delete a changelog entry.
     */
    public function destroy(int $id): RedirectResponse
    {
        $entry = ChangelogEntry::findOrFail($id);
        $title = $entry->title;
        $entry->delete();

        return redirect()
            ->route('changelog.dashboard.index')
            ->with('success', "Entry \"{$title}\" has been deleted.");
    }
}
