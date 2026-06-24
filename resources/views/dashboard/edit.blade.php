@extends('changelog::dashboard.layout')

@section('title', 'Edit Entry — Changelog Dashboard')

@section('content')

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('changelog.dashboard.index') }}" class="transition hover:text-brand-600">Dashboard</a>
        <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Edit Entry</span>
    </nav>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

        {{-- ── Main form (left 2/3) ────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('changelog.dashboard.update', $entry->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Title --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <label for="entry-title" class="block text-sm font-semibold text-gray-900 mb-2">Title</label>
                    <input type="text"
                           name="title"
                           id="entry-title"
                           value="{{ old('title', $entry->title) }}"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-brand-500 focus:ring-brand-500"
                           placeholder="A user-friendly title for this changelog entry…"
                           required>
                    @error('title')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Body --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <label for="entry-body" class="block text-sm font-semibold text-gray-900 mb-2">Description</label>
                    <p class="text-xs text-gray-500 mb-3">Expand on what changed. This is shown on the public changelog page below the title.</p>
                    <textarea name="body"
                              id="entry-body"
                              rows="8"
                              class="w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-brand-500 focus:ring-brand-500"
                              placeholder="Describe the change in detail…">{{ old('body', $entry->body) }}</textarea>
                    @error('body')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <label for="entry-type" class="block text-sm font-semibold text-gray-900 mb-2">Category</label>
                    <p class="text-xs text-gray-500 mb-3">How this change is categorised on the public changelog.</p>
                    <select name="type"
                            id="entry-type"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-brand-500 focus:ring-brand-500 sm:w-auto sm:min-w-[200px]">
                        <option value="">Uncategorised</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(old('type', $entry->type) === $type)>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Save Changes
                    </button>
                    <a href="{{ route('changelog.dashboard.index') }}"
                       class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-600 shadow-sm transition hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        {{-- ── Sidebar (right 1/3) ─────────────────────────────────── --}}
        <div class="space-y-6">

            {{-- Publish status card --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Status</h3>

                @if($entry->is_published)
                    <div class="mb-4 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-medium text-emerald-700">Published</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">
                        Published {{ $entry->published_at->diffForHumans() }}
                    </p>
                    <form method="POST" action="{{ route('changelog.dashboard.unpublish', $entry->id) }}">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
                            Unpublish
                        </button>
                    </form>
                @else
                    <div class="mb-4 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                        <span class="text-sm font-medium text-gray-500">Draft</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">
                        This entry is not visible on the public changelog.
                    </p>
                    <form method="POST" action="{{ route('changelog.dashboard.publish', $entry->id) }}">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                            Publish Now
                        </button>
                    </form>
                @endif
            </div>

            {{-- Commit details card --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Commit Details</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">SHA</dt>
                        <dd class="mt-0.5">
                            @if($entry->commit_url)
                                <a href="{{ $entry->commit_url }}" target="_blank" class="font-mono text-brand-600 transition hover:text-brand-700">
                                    {{ $entry->short_sha }}
                                    <svg class="ml-1 inline h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @else
                                <span class="font-mono text-gray-600">{{ $entry->short_sha }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Author</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $entry->author_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Committed</dt>
                        <dd class="mt-0.5 text-gray-700">
                            {{ $entry->committed_at ? $entry->committed_at->format('M j, Y \a\t g:ia') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Repository</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $entry->repository->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Original commit message card --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Original Commit Message</h3>
                <div class="rounded-lg bg-gray-50 p-4">
                    <pre class="whitespace-pre-wrap text-xs text-gray-600 font-mono leading-relaxed">{{ $entry->original_commit_message }}</pre>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="rounded-xl border border-red-200 bg-red-50/50 p-6">
                <h3 class="text-sm font-semibold text-red-900 mb-2">Danger Zone</h3>
                <p class="text-xs text-red-700/70 mb-4">This action cannot be undone.</p>
                <form method="POST"
                      action="{{ route('changelog.dashboard.destroy', $entry->id) }}"
                      x-data
                      @submit.prevent="if(confirm('Are you sure you want to permanently delete this entry?')) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-700 shadow-sm transition hover:bg-red-50">
                        Delete Entry
                    </button>
                </form>
            </div>
        </div>
    </div>

@endsection
