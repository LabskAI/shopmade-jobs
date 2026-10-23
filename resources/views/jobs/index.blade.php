@extends('layouts.app')

@section('title', 'Jobs')
@section('breadcrumb', 'Jobs')

@section('content')
    <x-page-header title="Jobs" kicker="Work">
        <x-slot:actions>
            <x-button href="{{ route('jobs.create') }}"><i data-lucide="plus" class="size-4"></i>New job</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="border-2 border-line bg-surface">
        <form method="GET">
            <x-toolbar title="Jobs" :count="$jobs->total()">
                <x-slot:right>
                    <x-input type="search" name="q" value="{{ request('q') }}" placeholder="Search customer" />
                    <x-select name="status" :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])" :selected="request('status')" placeholder="All statuses" onchange="this.form.submit()" />
                </x-slot:right>
            </x-toolbar>
        </form>
        <x-table :columns="[['label' => 'Customer'], ['label' => 'When'], ['label' => 'Status'], ['label' => 'Assigned']]">
            @foreach ($jobs as $job)
                <tr class="border-b border-line-soft">
                    <td class="px-4 py-3 text-control"><a href="{{ route('jobs.show', $job) }}">{{ $job->customer->name }}</a></td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $job->windowLabel() }}</td>
                    <td class="px-4 py-3"><x-status :state="$job->status->value" /></td>
                    <td class="px-4 py-3 text-control text-ink/70">{{ $job->assignee?->name ?? '—' }}</td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-empty title="No jobs match" body="Clear the filter or the search." />
            </x-slot:empty>
        </x-table>
        {{ $jobs->links() }}
    </div>
@endsection
