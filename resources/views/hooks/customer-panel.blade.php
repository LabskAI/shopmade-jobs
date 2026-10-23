<x-card class="lg:col-span-2" title="Recent jobs">
    @can('create', \Shopmade\Jobs\Models\Job::class)
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('jobs.create', ['customer_id' => $customer->id]) }}">New job</x-button>
        </x-slot:actions>
    @endcan
    @forelse ($jobs as $job)
        <div class="flex items-center justify-between border-b border-line-soft py-2 last:border-0">
            <a class="text-control" href="{{ route('jobs.show', $job) }}">{{ $job->scheduled_at?->format('M j, Y') ?? 'Unscheduled' }}</a>
            <x-status :state="$job->status->value" />
        </div>
    @empty
        <x-empty title="No jobs yet" body="Book the first job for this customer." />
    @endforelse
</x-card>
