<x-card title="Next visit" class="mb-4">
    @if ($nextVisit)
        <a href="{{ route('portal.jobs.show', $nextVisit) }}" class="block text-ink hover:text-ink">
            <p class="text-card-title">{{ $nextVisit->windowLabel() }}</p>
            <p class="mt-1 text-body">{{ $nextVisit->customer->name }}</p>
            @if ($nextVisit->customer->address)
                <p class="text-control text-ink/70">{{ $nextVisit->customer->address }}</p>
            @endif
            @if ($nextVisit->assignee)
                <p class="mt-1 text-control text-ink/70">Crew: {{ \Illuminate\Support\Str::of($nextVisit->assignee->name)->before(' ') }}</p>
            @endif
            <div class="mt-3"><x-status :state="$nextVisit->status->value" /></div>
        </a>
    @else
        <x-empty title="No visit scheduled" body="We'll show your next visit here as soon as it's booked." />
    @endif
    <x-slot:actions>
        <x-button variant="ghost" href="{{ route('portal.jobs.index') }}">All visits · {{ $openVisits }} open</x-button>
    </x-slot:actions>
</x-card>

@if ($upcomingVisits->isNotEmpty())
    <x-card title="Upcoming visits" class="mb-4">
        <ul class="divide-y divide-line-soft">
            @foreach ($upcomingVisits as $visit)
                <li>
                    <a href="{{ route('portal.jobs.show', $visit) }}" class="ds-hit flex items-center justify-between gap-3 py-3 text-ink hover:text-ink">
                        <span>
                            <span class="block text-control font-semibold">{{ $visit->windowLabel() }}</span>
                            <span class="block text-label text-ink/70">{{ $visit->customer->name }}</span>
                        </span>
                        <x-status :state="$visit->status->value" />
                    </a>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif

@if ($recentPhotos->isNotEmpty())
    <x-card title="Recent photos" class="mb-4">
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach ($recentPhotos as $photo)
                <x-jobs::photo-thumb :photo="$photo">
                    <a href="{{ route('portal.jobs.show', $photo->job) }}" class="mt-1 block truncate text-label">{{ $photo->job->customer->name }}</a>
                </x-jobs::photo-thumb>
            @endforeach
        </div>
    </x-card>
@endif
