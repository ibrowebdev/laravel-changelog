@extends('changelog::dashboard.layout')

@section('title', 'Repositories - Changelog')

@section('content')
<div class="space-y-10">

    {{-- Header --}}
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-6 text-gray-900">Repositories</h1>
            <p class="mt-2 text-sm text-gray-700">Manage the GitHub repositories connected to your changelog.</p>
        </div>
    </div>

    {{-- Error Messages --}}
    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul role="list" class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Add Repository Form --}}
    <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-5 sm:px-6">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Add New Repository</h3>
        </div>
        <div class="px-4 py-6 sm:px-6">
            <form action="{{ route('changelog.dashboard.store-repository') }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                    
                    {{-- Owner --}}
                    <div>
                        <label for="owner" class="block text-sm font-medium leading-6 text-gray-900">GitHub Owner / Org</label>
                        <div class="mt-2">
                            <input type="text" name="owner" id="owner" value="{{ old('owner') }}" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6" placeholder="e.g. ibrowebdev" required>
                        </div>
                    </div>

                    {{-- Repo --}}
                    <div>
                        <label for="repo" class="block text-sm font-medium leading-6 text-gray-900">Repository Name</label>
                        <div class="mt-2">
                            <input type="text" name="repo" id="repo" value="{{ old('repo') }}" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6" placeholder="e.g. dardaa-web" required>
                        </div>
                    </div>

                    {{-- Branch --}}
                    <div>
                        <label for="default_branch" class="block text-sm font-medium leading-6 text-gray-900">Branch</label>
                        <div class="mt-2">
                            <input type="text" name="default_branch" id="default_branch" value="{{ old('default_branch', 'main') }}" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6" required>
                        </div>
                    </div>

                    {{-- Secret --}}
                    <div>
                        <label for="webhook_secret" class="block text-sm font-medium leading-6 text-gray-900">Webhook Secret</label>
                        <div class="mt-2">
                            <input type="password" name="webhook_secret" id="webhook_secret" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6" required>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">This will be encrypted in your database.</p>
                    </div>

                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex justify-center rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        Add Repository
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Repositories List --}}
    <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Repository</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Branch</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Webhook URL</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($repositories as $repository)
                <tr>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                        {{ $repository->name }}
                        @if($repository->github_id)
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 ml-2">Connected</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-600/20 ml-2">Pending Webhook</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                            {{ $repository->default_branch }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded">
                            {{ url(config('changelog.route_prefix', 'changelog') . '/webhook') }}
                        </code>
                    </td>
                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                        <form action="{{ route('changelog.dashboard.destroy-repository', $repository->id) }}" method="POST" onsubmit="return confirm('Are you sure? This will delete the repository AND all its changelog entries forever.');" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-8 text-center text-sm text-gray-500">
                        No repositories registered yet. Add one above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
