<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('changelog.page_title', 'Changelog') }}</title>
    <meta name="description" content="{{ config('changelog.meta_description', 'See what\'s new — latest updates, fixes, and improvements.') }}">

    {{-- Tailwind CSS via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        },
                    },
                },
            },
        }
    </script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }

        /* Timeline connector line */
        .timeline-line {
            position: absolute;
            left: 7px;
            top: 24px;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #c7d2fe, #e0e7ff, transparent);
        }

        /* Subtle entry hover effect */
        .entry-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .entry-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        /* Fade-in animation for entries */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body class="min-h-full bg-gradient-to-br from-gray-50 via-white to-brand-50/30 text-gray-900 antialiased">

    {{-- Hero header --}}
    <header class="relative overflow-hidden border-b border-gray-100 bg-white/60 backdrop-blur-sm">
        {{-- Background decoration --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-100/40 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-purple-100/30 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-3xl px-6 py-16 text-center">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-4 py-1.5 text-xs font-semibold text-brand-700">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-500"></span>
                </span>
                Latest Updates
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">
                {{ config('changelog.page_title', 'Changelog') }}
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-lg text-gray-500">
                {{ config('changelog.page_subtitle', 'New updates and improvements. Follow along to see what\'s changed.') }}
            </p>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-12">

        {{-- Type filter pills --}}
        <nav class="mb-10 flex flex-wrap items-center gap-2">
            <a href="{{ route('changelog.public.index') }}"
               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition
                      {{ !$activeType ? 'bg-brand-600 text-white shadow-md shadow-brand-500/25' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}">
                All
            </a>
            @foreach($types as $type)
                @php
                    $pillColors = [
                        'added'    => 'bg-emerald-600 text-white shadow-emerald-500/25',
                        'changed'  => 'bg-blue-600 text-white shadow-blue-500/25',
                        'fixed'    => 'bg-amber-500 text-white shadow-amber-500/25',
                        'removed'  => 'bg-red-600 text-white shadow-red-500/25',
                        'security' => 'bg-purple-600 text-white shadow-purple-500/25',
                    ];
                    $activeClass = $pillColors[$type] ?? 'bg-gray-600 text-white';
                @endphp
                <a href="{{ route('changelog.public.index', ['type' => $type]) }}"
                   class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition
                          {{ $activeType === $type ? "{$activeClass} shadow-md" : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}">
                    {{ ucfirst($type) }}
                </a>
            @endforeach
        </nav>

        {{-- Entries timeline --}}
        @if($grouped->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-8 py-20 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">No entries yet</h3>
                <p class="mt-2 text-sm text-gray-500">Check back soon — we're always shipping improvements.</p>
            </div>
        @else
            <div class="space-y-12">
                @foreach($grouped as $date => $dateEntries)
                    <section class="animate-fade-in-up" style="animation-delay: {{ $loop->index * 0.1 }}s;">
                        {{-- Date header --}}
                        <div class="mb-6 flex items-center gap-4">
                            <h2 class="text-sm font-bold uppercase tracking-widest text-brand-600">{{ $date }}</h2>
                            <div class="h-px flex-1 bg-gradient-to-r from-brand-200 to-transparent"></div>
                        </div>

                        {{-- Entries for this date --}}
                        <div class="relative space-y-4 pl-8">
                            {{-- Timeline line --}}
                            <div class="timeline-line"></div>

                            @foreach($dateEntries as $entry)
                                <article class="entry-card relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                                    {{-- Timeline dot --}}
                                    <div class="absolute -left-8 top-7 flex h-4 w-4 items-center justify-center">
                                        @php
                                            $dotColors = [
                                                'added'    => 'bg-emerald-500',
                                                'changed'  => 'bg-blue-500',
                                                'fixed'    => 'bg-amber-500',
                                                'removed'  => 'bg-red-500',
                                                'security' => 'bg-purple-500',
                                            ];
                                            $dotColor = $dotColors[$entry->type] ?? 'bg-gray-400';
                                        @endphp
                                        <span class="h-3 w-3 rounded-full {{ $dotColor }} ring-4 ring-white"></span>
                                    </div>

                                    {{-- Entry header --}}
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="flex-1">
                                            {{-- Type badge --}}
                                            @if($entry->type)
                                                @php
                                                    $badgeColors = [
                                                        'added'    => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                                        'changed'  => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                                        'fixed'    => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                                        'removed'  => 'bg-red-50 text-red-700 ring-red-600/20',
                                                        'security' => 'bg-purple-50 text-purple-700 ring-purple-600/20',
                                                    ];
                                                    $badgeColor = $badgeColors[$entry->type] ?? 'bg-gray-50 text-gray-600 ring-gray-500/20';
                                                @endphp
                                                <span class="mb-2 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badgeColor }}">
                                                    {{ $entry->type_label }}
                                                </span>
                                            @endif

                                            <h3 class="text-lg font-semibold text-gray-900">{{ $entry->title }}</h3>
                                        </div>

                                        {{-- Commit link --}}
                                        @if($entry->commit_url)
                                            <a href="{{ $entry->commit_url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 font-mono text-xs text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                                               title="View commit on GitHub">
                                                {{ $entry->short_sha }}
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>

                                    {{-- Body --}}
                                    @if($entry->body)
                                        <div class="mt-3 text-sm leading-relaxed text-gray-600">
                                            {!! nl2br(e($entry->body)) !!}
                                        </div>
                                    @endif

                                    {{-- Footer meta --}}
                                    <div class="mt-4 flex items-center gap-4 text-xs text-gray-400">
                                        <span class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {{ $entry->author_name }}
                                        </span>
                                        <span>{{ $entry->published_at->diffForHumans() }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($entries->hasPages())
                <div class="mt-12">
                    {{ $entries->links() }}
                </div>
            @endif
        @endif
    </main>

    {{-- Footer --}}
    <footer class="border-t border-gray-100 bg-white/40 backdrop-blur-sm">
        <div class="mx-auto max-w-3xl px-6 py-8 text-center text-xs text-gray-400">
            Powered by <a href="https://github.com/ibrohim/laravel-changelog" target="_blank" class="font-medium text-brand-500 transition hover:text-brand-600">Laravel Changelog</a>
        </div>
    </footer>

</body>
</html>
