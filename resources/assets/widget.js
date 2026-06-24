/**
 * Laravel Changelog — Embeddable Widget
 *
 * Drop this script tag into any HTML page to render a changelog widget:
 *
 *   <div id="changelog-widget"></div>
 *   <script
 *     src="https://yourapp.com/changelog/widget.js"
 *     data-changelog-url="https://yourapp.com/changelog/api/entries"
 *     data-limit="5"
 *     data-type=""
 *     data-theme="light"
 *   ></script>
 *
 * Configuration via data attributes:
 *   - data-changelog-url : (required) Full URL to the JSON API endpoint
 *   - data-container     : (optional) CSS selector for the container element. Default: "#changelog-widget"
 *   - data-limit         : (optional) Number of entries to display. Default: 5
 *   - data-type          : (optional) Filter by type: added, changed, fixed, removed, security
 *   - data-theme         : (optional) "light" or "dark". Default: "light"
 *
 * The widget uses Shadow DOM to fully encapsulate its styles — it will never
 * leak into or be affected by the host page's CSS.
 */
(function () {
    'use strict';

    // ── 1. Read configuration from the script tag ───────────────────────
    //
    // document.currentScript gives us the <script> element itself, so we
    // can read data attributes from it. This must be captured immediately
    // because document.currentScript becomes null after the script finishes
    // executing.
    var scriptTag = document.currentScript;

    var config = {
        apiUrl:    scriptTag.getAttribute('data-changelog-url') || '',
        container: scriptTag.getAttribute('data-container') || '#changelog-widget',
        limit:     parseInt(scriptTag.getAttribute('data-limit'), 10) || 5,
        type:      scriptTag.getAttribute('data-type') || '',
        theme:     scriptTag.getAttribute('data-theme') || 'light',
    };

    if (!config.apiUrl) {
        console.error('[Changelog Widget] Missing required "data-changelog-url" attribute on the script tag.');
        return;
    }

    // ── 2. Styles ───────────────────────────────────────────────────────
    //
    // All styles are injected into the Shadow DOM, so they're fully
    // encapsulated. The host page's CSS cannot interfere, and these
    // styles cannot leak out.
    var STYLES = /* css */`
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :host {
            display: block;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* ── Theme: Light ────────────────── */
        .cl-widget {
            --cl-bg: #ffffff;
            --cl-bg-subtle: #f9fafb;
            --cl-border: #e5e7eb;
            --cl-text: #111827;
            --cl-text-secondary: #6b7280;
            --cl-text-muted: #9ca3af;
            --cl-brand: #4f46e5;
            --cl-brand-light: #eef2ff;
            --cl-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --cl-shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.08);
            --cl-radius: 12px;
            --cl-radius-sm: 8px;
            --cl-radius-full: 9999px;
        }

        /* ── Theme: Dark ─────────────────── */
        .cl-widget.cl-dark {
            --cl-bg: #1f2937;
            --cl-bg-subtle: #111827;
            --cl-border: #374151;
            --cl-text: #f9fafb;
            --cl-text-secondary: #d1d5db;
            --cl-text-muted: #9ca3af;
            --cl-brand: #818cf8;
            --cl-brand-light: #312e81;
            --cl-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            --cl-shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .cl-widget {
            background: var(--cl-bg);
            border: 1px solid var(--cl-border);
            border-radius: var(--cl-radius);
            box-shadow: var(--cl-shadow);
            overflow: hidden;
        }

        /* ── Header ──────────────────────── */
        .cl-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--cl-border);
            background: var(--cl-bg-subtle);
        }

        .cl-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cl-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--cl-radius-sm);
            background: linear-gradient(135deg, #6366f1, #4338ca);
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3);
        }

        .cl-logo svg {
            width: 18px;
            height: 18px;
            color: white;
        }

        .cl-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--cl-text);
            letter-spacing: -0.01em;
        }

        .cl-view-all {
            font-size: 12px;
            font-weight: 600;
            color: var(--cl-brand);
            text-decoration: none;
            padding: 4px 10px;
            border-radius: var(--cl-radius-full);
            transition: background 0.15s ease;
        }

        .cl-view-all:hover {
            background: var(--cl-brand-light);
        }

        /* ── Entry list ──────────────────── */
        .cl-entries {
            list-style: none;
        }

        .cl-entry {
            display: block;
            padding: 14px 20px;
            border-bottom: 1px solid var(--cl-border);
            transition: background 0.15s ease;
        }

        .cl-entry:last-child {
            border-bottom: none;
        }

        .cl-entry:hover {
            background: var(--cl-bg-subtle);
        }

        .cl-entry-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }

        .cl-entry-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--cl-text);
            line-height: 1.4;
        }

        .cl-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: var(--cl-radius-full);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .cl-badge-added    { background: #ecfdf5; color: #065f46; }
        .cl-badge-changed  { background: #eff6ff; color: #1e40af; }
        .cl-badge-fixed    { background: #fffbeb; color: #92400e; }
        .cl-badge-removed  { background: #fef2f2; color: #991b1b; }
        .cl-badge-security { background: #f5f3ff; color: #5b21b6; }

        .cl-dark .cl-badge-added    { background: #064e3b; color: #6ee7b7; }
        .cl-dark .cl-badge-changed  { background: #1e3a5f; color: #93c5fd; }
        .cl-dark .cl-badge-fixed    { background: #78350f; color: #fcd34d; }
        .cl-dark .cl-badge-removed  { background: #7f1d1d; color: #fca5a5; }
        .cl-dark .cl-badge-security { background: #4c1d95; color: #c4b5fd; }

        .cl-entry-body {
            font-size: 12px;
            color: var(--cl-text-secondary);
            line-height: 1.5;
            margin-top: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cl-entry-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
            font-size: 11px;
            color: var(--cl-text-muted);
        }

        .cl-entry-meta a {
            color: var(--cl-text-muted);
            text-decoration: none;
            font-family: 'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace;
            transition: color 0.15s ease;
        }

        .cl-entry-meta a:hover {
            color: var(--cl-brand);
        }

        /* ── Loading state ───────────────── */
        .cl-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: var(--cl-text-muted);
            font-size: 13px;
            gap: 8px;
        }

        .cl-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--cl-border);
            border-top-color: var(--cl-brand);
            border-radius: 50%;
            animation: cl-spin 0.6s linear infinite;
        }

        @keyframes cl-spin {
            to { transform: rotate(360deg); }
        }

        /* ── Error state ─────────────────── */
        .cl-error {
            padding: 24px 20px;
            text-align: center;
            color: var(--cl-text-muted);
            font-size: 13px;
        }

        /* ── Empty state ─────────────────── */
        .cl-empty {
            padding: 32px 20px;
            text-align: center;
            color: var(--cl-text-muted);
            font-size: 13px;
        }

        .cl-empty-icon {
            width: 36px;
            height: 36px;
            margin: 0 auto 10px;
            color: var(--cl-border);
        }

        /* ── Footer ──────────────────────── */
        .cl-footer {
            padding: 10px 20px;
            text-align: center;
            font-size: 10px;
            color: var(--cl-text-muted);
            border-top: 1px solid var(--cl-border);
            background: var(--cl-bg-subtle);
        }

        .cl-footer a {
            color: var(--cl-brand);
            text-decoration: none;
            font-weight: 600;
        }

        .cl-footer a:hover {
            text-decoration: underline;
        }

        /* ── Fade-in animation ───────────── */
        @keyframes cl-fade-in {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .cl-animate {
            animation: cl-fade-in 0.3s ease forwards;
            opacity: 0;
        }
    `;

    // ── 3. Build the widget ─────────────────────────────────────────────

    /**
     * Initialise the widget once the DOM is ready.
     * We wait for DOMContentLoaded in case the script is in <head>.
     */
    function init() {
        var container = document.querySelector(config.container);

        if (!container) {
            console.error('[Changelog Widget] Container element not found: ' + config.container);
            return;
        }

        // Create Shadow DOM for style encapsulation.
        // The widget's styles can't leak into the host page, and the host
        // page's styles can't break the widget.
        var shadow = container.attachShadow({ mode: 'open' });

        // Inject styles
        var styleEl = document.createElement('style');
        styleEl.textContent = STYLES;
        shadow.appendChild(styleEl);

        // Create the widget root
        var widget = document.createElement('div');
        widget.className = 'cl-widget' + (config.theme === 'dark' ? ' cl-dark' : '');
        shadow.appendChild(widget);

        // Derive the public changelog URL from the API URL
        // e.g. "https://app.com/changelog/api/entries" → "https://app.com/changelog"
        var publicUrl = config.apiUrl.replace(/\/api\/entries\/?$/, '');

        // Render header
        widget.innerHTML = renderHeader(publicUrl);

        // Show loading state
        var contentArea = document.createElement('div');
        contentArea.className = 'cl-content';
        contentArea.innerHTML = renderLoading();
        widget.appendChild(contentArea);

        // Render footer
        var footer = document.createElement('div');
        footer.className = 'cl-footer';
        footer.innerHTML = 'Powered by <a href="https://github.com/ibrohim/laravel-changelog" target="_blank" rel="noopener">Laravel Changelog</a>';
        widget.appendChild(footer);

        // Fetch entries
        fetchEntries(contentArea, publicUrl);
    }

    /**
     * Render the widget header HTML.
     */
    function renderHeader(publicUrl) {
        return '' +
            '<div class="cl-header">' +
                '<div class="cl-header-left">' +
                    '<div class="cl-logo">' +
                        '<svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />' +
                        '</svg>' +
                    '</div>' +
                    '<span class="cl-title">Changelog</span>' +
                '</div>' +
                '<a href="' + publicUrl + '" target="_blank" rel="noopener" class="cl-view-all">View all →</a>' +
            '</div>';
    }

    /**
     * Render the loading spinner HTML.
     */
    function renderLoading() {
        return '' +
            '<div class="cl-loading">' +
                '<div class="cl-spinner"></div>' +
                'Loading…' +
            '</div>';
    }

    /**
     * Fetch entries from the JSON API and render them.
     */
    function fetchEntries(contentArea, publicUrl) {
        var url = config.apiUrl + '?limit=' + config.limit;

        if (config.type) {
            url += '&type=' + encodeURIComponent(config.type);
        }

        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (result) {
                var entries = result.data || [];

                if (entries.length === 0) {
                    contentArea.innerHTML = renderEmpty();
                    return;
                }

                contentArea.innerHTML = renderEntries(entries);
            })
            .catch(function (error) {
                console.error('[Changelog Widget] Failed to load entries:', error);
                contentArea.innerHTML = renderError();
            });
    }

    /**
     * Render the entries list HTML.
     */
    function renderEntries(entries) {
        var html = '<ul class="cl-entries">';

        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];
            var delay = (i * 0.05).toFixed(2);

            html += '<li class="cl-entry cl-animate" style="animation-delay: ' + delay + 's;">';
            html +=   '<div class="cl-entry-header">';
            html +=     '<span class="cl-entry-title">' + escapeHtml(entry.title) + '</span>';

            if (entry.type) {
                html += '<span class="cl-badge cl-badge-' + escapeHtml(entry.type) + '">' + escapeHtml(entry.type_label) + '</span>';
            }

            html +=   '</div>';

            if (entry.body) {
                html += '<div class="cl-entry-body">' + escapeHtml(entry.body) + '</div>';
            }

            html += '<div class="cl-entry-meta">';
            html +=   '<span>' + escapeHtml(entry.author) + '</span>';

            if (entry.commit_url) {
                html += '<a href="' + escapeHtml(entry.commit_url) + '" target="_blank" rel="noopener">' + escapeHtml(entry.commit_sha) + '</a>';
            }

            html +=   '<span>' + escapeHtml(entry.published_at_human) + '</span>';
            html += '</div>';

            html += '</li>';
        }

        html += '</ul>';
        return html;
    }

    /**
     * Render the empty state HTML.
     */
    function renderEmpty() {
        return '' +
            '<div class="cl-empty">' +
                '<svg class="cl-empty-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />' +
                '</svg>' +
                '<div>No entries yet</div>' +
            '</div>';
    }

    /**
     * Render the error state HTML.
     */
    function renderError() {
        return '' +
            '<div class="cl-error">' +
                'Unable to load changelog entries.' +
            '</div>';
    }

    /**
     * Escape HTML special characters to prevent XSS.
     * This is critical because we're rendering data from an API response.
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── 4. Bootstrap ────────────────────────────────────────────────────
    //
    // If the DOM is already ready (script at bottom of body), init immediately.
    // Otherwise wait for DOMContentLoaded (script in <head>).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
