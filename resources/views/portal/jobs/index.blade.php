@extends('layouts.portal')

@section('title', 'Visits')

@section('content')
    <x-page-header title="Visits" kicker="Client portal" />

    <x-card title="Upcoming" class="mb-4">
        @forelse ($upcoming as $visit)
            <a href="{{ route('portal.jobs.show', $visit) }}" class="ds-hit block border-b border-line-soft py-3 text-ink last:border-0 hover:text-ink">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-control font-semibold">{{ $visit->windowLabel() }}</span>
                    <x-status :state="$visit->status->value" />
                </div>
                <p class="mt-1 text-label text-ink/70">
                    {{ $visit->customer->name }}
                    @if ($visit->assignee)
                        · Crew: {{ \Illuminate\Support\Str::of($visit->assignee->name)->before(' ') }}
                    @endif
                    @if ($visit->client_photos_count > 0)
                        · {{ $visit->client_photos_count }} {{ \Illuminate\Support\Str::plural('photo', $visit->client_photos_count) }}
                    @endif
                </p>
            </a>
        @empty
            <x-empty title="No upcoming visits" body="Booked visits for your sites will show up here." />
        @endforelse
    </x-card>

    <x-card title="Past">
        @forelse ($past as $visit)
            <a href="{{ route('portal.jobs.show', $visit) }}" class="ds-hit block border-b border-line-soft py-2 text-ink last:border-0 hover:text-ink">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-control">{{ $visit->windowLabel() }}</span>
                    <x-status :state="$visit->status->value" />
                </div>
                <p class="text-label text-ink/70">
                    {{ $visit->customer->name }}
                    @if ($visit->client_photos_count > 0)
                        · {{ $visit->client_photos_count }} {{ \Illuminate\Support\Str::plural('photo', $visit->client_photos_count) }}
                    @endif
                </p>
            </a>
        @empty
            <x-empty title="No past visits" body="Completed visits will be listed here." />
        @endforelse
    </x-card>
@endsection
