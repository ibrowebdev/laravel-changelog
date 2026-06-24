@extends('changelog::dashboard.layout')

@section('title', 'Changelog Dashboard')

@section('content')

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Changelog Entries</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your changelog entries. Edit titles, categorise, and publish to your public changelog.</p>
    </div>

    {{-- Filters --}}
    <div x-data="{ filtersOpen: {{ collect($filters)->filter()->isNotEmpty() ? 'true' : 'false' }} }"
         class="mb-6">

        <button @click="filtersOpen = !filtersOpen"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                type="button">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
            </svg>
            Filters
            @if(collect($filters)->filter()->isNotEmpty())
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                    {{ collect($filters)->filter()->count() }}
                </span>
            @endif
        </button>

        <div x-show="filtersOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-cloak
             class="mt-4">
            <form method="GET" action="{{ route('changelog.dashboard.index') }}"
                  class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">

                {{-- Status --}}
                <div>
                    <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Status</label>
                    <select name="status" id="filter-status"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All statuses</option>
                        <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                    </select>
                </div>

                {{-- Type --}}
                <div>
                    <label for="filter-type" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Type</label>
                    <select name="type" id="filter-type"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All types</option>
                        @foreach(\Ibrohim\Changelog\Models\ChangelogEntry::validTypes() as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Repository --}}
                <div>
                    <label for="filter-repository" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Repository</label>
                    <select name="repository" id="filter-repository"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All repositories</option>
                        @foreach($repositories as $repo)
                            <option value="{{ $repo->id }}" @selected(($filters['repository'] ?? '') == $repo->id)>{{ $repo->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div>
                    <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Search</label>
                    <input type="text" name="search" id="filter-search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Title, message, or author…"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                {{-- Actions --}}
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                        Apply Filters
                    </button>
                    <a href="{{ route('changelog.dashboard.index') }}"
                       class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 shadow-sm transition hover:bg-gray-50">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Entries table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        @if($entries->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <h3 class="mt-4 text-sm font-semibold text-gray-900">No entries found</h3>
                <p class="mt-1 text-sm text-gray-500">Entries will appear here once commits are received via webhook.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Entry</th>
                        <th class="hidden px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 sm:table-cell">Type</th>
                        <th class="hidden px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 md:table-cell">Repository</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($entries as $entry)
                        <tr class="group transition hover:bg-gray-50/50">
                            {{-- Entry info --}}
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('changelog.dashboard.edit', $entry->id) }}"
                                           class="text-sm font-semibold text-gray-900 transition hover:text-brand-600">
                                            {{ Str::limit($entry->title, 60) }}
                                        </a>
                                        <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                {{ $entry->author_name }}
                                            </span>
                                            <span class="font-mono text-gray-400" title="{{ $entry->commit_sha }}">
                                                {{ $entry->short_sha }}
                                            </span>
                                            @if($entry->committed_at)
                                                <span>{{ $entry->committed_at->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Type badge --}}
                            <td class="hidden px-6 py-4 sm:table-cell">
                                @php
                                    $typeColors = [
                                        'added'    => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        'changed'  => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                        'fixed'    => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                        'removed'  => 'bg-red-50 text-red-700 ring-red-600/20',
                                        'security' => 'bg-purple-50 text-purple-700 ring-purple-600/20',
                                    ];
                                    $colorClass = $typeColors[$entry->type] ?? 'bg-gray-50 text-gray-600 ring-gray-500/20';
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $colorClass }}">
                                    {{ $entry->type_label }}
                                </span>
                            </td>

                            {{-- Repository --}}
                            <td class="hidden px-6 py-4 md:table-cell">
                                <span class="text-sm text-gray-600">{{ $entry->repository->name ?? '—' }}</span>
                            </td>

                            {{-- Published status --}}
                            <td class="px-6 py-4">
                                @if($entry->is_published)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Published
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right" x-data="{ open: false }">
                                <div class="relative inline-block text-left">
                                    <button @click="open = !open"
                                            @click.outside="open = false"
                                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-gray-400 shadow-sm transition hover:bg-gray-50 hover:text-gray-600"
                                            type="button">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                                        </svg>
                                    </button>

                                    <div x-show="open"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         x-cloak
                                         class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5">

                                        <a href="{{ route('changelog.dashboard.edit', $entry->id) }}"
                                           class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                            Edit
                                        </a>

                                        @if($entry->is_published)
                                            <form method="POST" action="{{ route('changelog.dashboard.unpublish', $entry->id) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                    </svg>
                                                    Unpublish
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('changelog.dashboard.publish', $entry->id) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-emerald-700 transition hover:bg-emerald-50">
                                                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    Publish
                                                </button>
                                            </form>
                                        @endif

                                        <div class="my-1 border-t border-gray-100"></div>

                                        <form method="POST"
                                              action="{{ route('changelog.dashboard.destroy', $entry->id) }}"
                                              x-data
                                              @submit.prevent="if(confirm('Are you sure you want to delete this entry?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 transition hover:bg-red-50">
                                                <svg class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($entries->hasPages())
                <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-4">
                    {{ $entries->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection
