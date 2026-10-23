@if ($myJobsToday !== null)
    <x-card title="My jobs today" class="mb-6">
        <x-slot:actions>
            <x-button variant="ghost" href="{{ route('schedule.index') }}">Schedule</x-button>
        </x-slot:actions>
        @forelse ($myJobsToday as $job)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft py-4 last:border-0">
                <div class="min-w-0">
                    <p class="text-control tabular-nums text-ink">{{ $job->windowLabel() }}</p>
                    <p class="text-card-title"><a href="{{ route('jobs.show', $job) }}">{{ $job->customer->name }}</a></p>
                    <p class="text-label text-ink/70">{{ $job->customer->address ?: '—' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status :state="$job->status->value" />
                    @if ($next = $job->nextStatus())
                        <form method="POST" action="{{ route('jobs.status.store', $job) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $next->value }}">
                            <x-button type="submit">{{ $next === \Shopmade\Jobs\Enums\JobStatus::InProgress ? 'Start' : 'Mark done' }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <x-empty title="Nothing on your board today" body="Jobs assigned to you and scheduled for today show up here." />
        @endforelse
    </x-card>
@endif

<x-card title="Upcoming jobs · {{ $openJobs }} open" class="mb-6">
    <x-slot:actions>
        <x-button variant="ghost" href="{{ route('schedule.index') }}">Schedule</x-button>
        <x-button variant="ghost" href="{{ route('jobs.index') }}">View all</x-button>
        @can('create', \Shopmade\Jobs\Models\Job::class)
            <x-button href="{{ route('jobs.create') }}"><i data-lucide="plus" class="size-4"></i>New job</x-button>
        @endcan
    </x-slot:actions>
    <x-table :columns="[['label' => 'Customer'], ['label' => 'When'], ['label' => 'Status'], ['label' => 'Assigned']]">
        @foreach ($upcomingJobs as $job)
            <tr class="border-b border-line-soft">
                <td class="px-4 py-3 text-control"><a href="{{ route('jobs.show', $job) }}">{{ $job->customer->name }}</a></td>
                <td class="px-4 py-3 text-control text-ink/70">{{ $job->scheduled_at?->format('M j, g:ia') ?? 'Unscheduled' }}</td>
                <td class="px-4 py-3"><x-status :state="$job->status->value" /></td>
                <td class="px-4 py-3 text-control text-ink/70">{{ $job->assignee?->name ?? '—' }}</td>
            </tr>
        @endforeach
        <x-slot:empty>
            <x-empty title="No jobs yet" body="Create a job to see it on the board." />
        </x-slot:empty>
    </x-table>
</x-card>
