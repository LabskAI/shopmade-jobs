@extends('layouts.app')

@section('title', 'Schedule')
@section('breadcrumb', 'Schedule')

@section('content')
    <x-page-header title="Schedule" kicker="Work">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('schedule.index', array_filter(['week' => $previousWeek, 'crew' => $crewId])) }}">
                <i data-lucide="chevron-left" class="size-4"></i>Prev
            </x-button>
            <x-button variant="secondary" href="{{ route('schedule.index', array_filter(['crew' => $crewId])) }}">This week</x-button>
            <x-button variant="secondary" href="{{ route('schedule.index', array_filter(['week' => $nextWeek, 'crew' => $crewId])) }}">
                Next<i data-lucide="chevron-right" class="size-4"></i>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <p class="text-body text-ink/70">{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</p>
        <form method="GET" class="w-full sm:w-64">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <x-field label="Crew" for="crew">
                <x-select id="crew" name="crew" :options="$crew->pluck('name', 'id')" :selected="$crewId" placeholder="All crew" onchange="this.form.submit()" />
            </x-field>
        </form>
    </div>

    <div class="space-y-4">
        @foreach ($days as $day)
            <x-card>
                <x-slot:actions>
                    @if ($day['date']->isToday())
                        <span class="inline-flex bg-ink px-2 py-1 text-micro uppercase text-bg">Today</span>
                    @endif
                </x-slot:actions>
                <x-slot:title>{{ $day['date']->format('D, M j') }}</x-slot:title>
                @forelse ($day['jobs'] as $job)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="text-control tabular-nums text-ink">
                                {{ $job->scheduled_at->format('g:ia') }}@if ($job->scheduled_end_at)–{{ $job->scheduled_end_at->format('g:ia') }}@endif
                            </p>
                            <p class="text-body"><a href="{{ route('jobs.show', $job) }}">{{ $job->customer->name }}</a></p>
                            <p class="text-label text-ink/70">{{ $job->customer->address ?: '—' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($job->assignee)
                                <span class="text-control text-ink/70">{{ $job->assignee->name }}</span>
                            @else
                                <span class="text-control text-warn-800">Unassigned</span>
                            @endif
                            <x-status :state="$job->status->value" />
                        </div>
                    </div>
                @empty
                    <p class="text-control text-ink/55">Nothing scheduled</p>
                @endforelse
            </x-card>
        @endforeach

        <x-card title="Unscheduled">
            @forelse ($unscheduled as $job)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="text-body"><a href="{{ route('jobs.show', $job) }}">{{ $job->customer->name }}</a></p>
                        <p class="text-label text-ink/70">{{ $job->customer->address ?: '—' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($job->assignee)
                            <span class="text-control text-ink/70">{{ $job->assignee->name }}</span>
                        @else
                            <span class="text-control text-warn-800">Unassigned</span>
                        @endif
                        <x-status :state="$job->status->value" />
                    </div>
                </div>
            @empty
                <x-empty title="Everything is on the calendar" body="Open jobs without a date will show up here." />
            @endforelse
        </x-card>
    </div>
@endsection
