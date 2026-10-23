@extends('layouts.portal')

@section('title', 'Visit')

@section('content')
    <x-page-header title="Visit · {{ $job->customer->name }}" kicker="Client portal">
        <x-slot:actions>
            <x-status :state="$job->status->value" />
        </x-slot:actions>
    </x-page-header>

    <x-card title="Details" class="mb-4">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div>
                <dt class="text-label text-ink/70">When</dt>
                <dd class="text-control">{{ $job->windowLabel() }}</dd>
            </div>
            <div>
                <dt class="text-label text-ink/70">Address</dt>
                <dd class="text-control">{{ $job->customer->address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-label text-ink/70">Crew</dt>
                <dd class="text-control">{{ $job->assignee ? \Illuminate\Support\Str::of($job->assignee->name)->before(' ') : 'To be assigned' }}</dd>
            </div>
            @if ($job->notes)
                <div class="sm:col-span-2">
                    <dt class="text-label text-ink/70">Notes</dt>
                    <dd class="text-control whitespace-pre-wrap">{{ $job->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    <x-card title="Photos" class="mb-4">
        @if ($job->clientPhotos->isEmpty())
            <x-empty title="No photos yet" body="Your crew will add before and after photos here." />
        @else
            @foreach (\Shopmade\Jobs\Enums\JobPhotoKind::cases() as $kind)
                @if ($photoGroups->has($kind->value))
                    <h3 class="mb-2 text-micro uppercase text-ink/70 {{ $loop->first ? '' : 'mt-5' }}">{{ $kind->label() }}</h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($photoGroups->get($kind->value) as $photo)
                            <x-jobs::photo-thumb :photo="$photo" />
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
    </x-card>

    <x-card title="Conversation">
        @forelse ($job->comments as $comment)
            <div class="border-b border-line-soft py-3 last:border-0">
                <div class="text-micro uppercase text-ink/45">{{ $comment->user?->name }} · {{ $comment->created_at->diffForHumans() }}</div>
                <p class="mt-1 text-body whitespace-pre-wrap">{{ $comment->body }}</p>
            </div>
        @empty
            <p class="text-control text-ink/55">No messages yet.</p>
        @endforelse

        <form method="POST" action="{{ route('portal.jobs.comments.store', $job) }}" class="mt-4">
            @csrf
            <x-field label="Send a message" for="body" :error="$errors->first('body')">
                <x-textarea id="body" name="body" required rows="3" :error="$errors->has('body')">{{ old('body') }}</x-textarea>
            </x-field>
            <div class="mt-3">
                <x-button type="submit"><i data-lucide="send" class="size-4"></i>Send</x-button>
            </div>
        </form>
    </x-card>
@endsection
