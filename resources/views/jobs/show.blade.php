@extends('layouts.app')

@section('title', 'Job')
@section('breadcrumb', 'Jobs / '.$job->customer->name)

@section('content')
    @php
        $nextStatus = $job->nextStatus();
        $photoKinds = collect(\Shopmade\Jobs\Enums\JobPhotoKind::cases())->mapWithKeys(fn ($kind) => [$kind->value => $kind->label()]);
        $openPanel = $errors->hasAny(['scheduled_at', 'scheduled_end_at'])
            ? 'reschedule'
            : ($errors->hasAny(['photos', 'photos.*', 'kind', 'caption']) ? 'photos' : null);
    @endphp

    <x-page-header title="{{ $job->customer->name }}" kicker="Work">
        <x-slot:actions>
            @pluginAttachments('jobs', $job)
            @if (Route::has('quotes.create'))
                <x-button variant="secondary" href="{{ route('quotes.create', ['customer_id' => $job->customer_id, 'job_id' => $job->id]) }}">New quote</x-button>
            @endif
            @if (Route::has('invoices.create') && auth()->user()->canManageCoreTool('invoices'))
                <x-button variant="secondary" href="{{ route('invoices.create', ['customer_id' => $job->customer_id, 'job_id' => $job->id]) }}">New invoice</x-button>
            @endif
            @can('update', $job)
                <x-button href="{{ route('jobs.edit', $job) }}">Edit</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4">
            <x-alert tone="ok" :title="session('status')" />
        </div>
    @endif

    @if (! $job->assignee)
        <div class="mb-4">
            <x-alert tone="warn" title="Crew unassigned">This job has nobody assigned.</x-alert>
        </div>
    @endif

    @can('update', $job)
        <div class="mb-6 border-2 border-line bg-surface p-4" x-data="{ panel: @js($openPanel) }">
            <div class="flex flex-wrap gap-2">
                @if ($nextStatus)
                    <form method="POST" action="{{ route('jobs.status.store', $job) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $nextStatus->value }}">
                        <x-button type="submit">
                            <i data-lucide="{{ $nextStatus === \Shopmade\Jobs\Enums\JobStatus::Done ? 'check' : 'play' }}" class="size-4"></i>
                            {{ $nextStatus === \Shopmade\Jobs\Enums\JobStatus::Done ? 'Mark done' : 'Start' }}
                        </x-button>
                    </form>
                @endif
                <x-button type="button" variant="secondary" x-on:click="panel = panel === 'reschedule' ? null : 'reschedule'">
                    <i data-lucide="calendar" class="size-4"></i>
                    Reschedule
                </x-button>
                <x-button type="button" variant="secondary" x-on:click="panel = panel === 'photos' ? null : 'photos'">
                    <i data-lucide="camera" class="size-4"></i>
                    Add photos
                </x-button>
                @if ($job->isOpen())
                    <x-button type="button" variant="ghost" x-on:click="$dispatch('open-modal', 'cancel-job')">Cancel job</x-button>
                @endif
            </div>

            <form method="POST" action="{{ route('jobs.schedule.store', $job) }}" class="mt-4 border-t-2 border-line pt-4" x-show="panel === 'reschedule'" x-cloak>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Scheduled" for="reschedule_at" :error="$errors->first('scheduled_at')" hint="Leave empty to unschedule.">
                        <x-input
                            id="reschedule_at"
                            type="datetime-local"
                            name="scheduled_at"
                            value="{{ old('scheduled_at', $job->scheduled_at?->format('Y-m-d\TH:i')) }}"
                            :error="$errors->has('scheduled_at')"
                        />
                    </x-field>
                    <x-field label="Ends" for="reschedule_end_at" :error="$errors->first('scheduled_end_at')">
                        <x-input
                            id="reschedule_end_at"
                            type="datetime-local"
                            name="scheduled_end_at"
                            value="{{ old('scheduled_end_at', $job->scheduled_end_at?->format('Y-m-d\TH:i')) }}"
                            :error="$errors->has('scheduled_end_at')"
                        />
                    </x-field>
                </div>
                <div class="mt-3 flex gap-2">
                    <x-button type="submit">Save schedule</x-button>
                    <x-button type="button" variant="secondary" x-on:click="panel = null">Close</x-button>
                </div>
            </form>

            <form method="POST" action="{{ route('jobs.photos.store', $job) }}" enctype="multipart/form-data" class="mt-4 border-t-2 border-line pt-4" x-show="panel === 'photos'" x-cloak>
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field class="sm:col-span-3" label="Photos" for="photos" :error="$errors->first('photos') ?: $errors->first('photos.*')" hint="JPG, PNG or WebP, up to 8 MB each, 10 at a time.">
                        <input
                            id="photos"
                            type="file"
                            name="photos[]"
                            multiple
                            required
                            accept="image/*"
                            capture="environment"
                            class="ds-hit w-full border-2 border-line bg-surface px-3 py-2 text-control text-ink"
                        >
                    </x-field>
                    <x-field label="Kind" for="photo_kind" :error="$errors->first('kind')">
                        <x-select id="photo_kind" name="kind" :options="$photoKinds" :selected="old('kind', 'other')" :error="$errors->has('kind')" />
                    </x-field>
                    <x-field class="sm:col-span-2" label="Caption" for="photo_caption" :error="$errors->first('caption')">
                        <x-input id="photo_caption" name="caption" value="{{ old('caption') }}" placeholder="Optional" :error="$errors->has('caption')" />
                    </x-field>
                    <div class="sm:col-span-3">
                        <input type="hidden" name="client_visible" value="0">
                        <x-checkbox name="client_visible" value="1" :checked="old('client_visible', true)" label="Visible to client" />
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <x-button type="submit">
                        <i data-lucide="upload" class="size-4"></i>
                        Upload
                    </x-button>
                    <x-button type="button" variant="secondary" x-on:click="panel = null">Close</x-button>
                </div>
            </form>
        </div>

        @if ($job->isOpen())
            <x-modal name="cancel-job" title="Cancel this job?">
                <p>The job stays on the list as cancelled. The client sees it as cancelled in the portal.</p>
                <x-slot:footer>
                    <form method="POST" action="{{ route('jobs.status.store', $job) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ \Shopmade\Jobs\Enums\JobStatus::Cancelled->value }}">
                        <x-button type="submit">Cancel job</x-button>
                    </form>
                    <x-button type="button" variant="secondary" x-on:click="open = false">Keep it</x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    @endcan

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Status">
            <x-status :state="$job->status->value" />
            <dl class="mt-4 space-y-3 text-body">
                <div>
                    <dt class="text-label text-ink/70">When</dt>
                    <dd>{{ $job->windowLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Assigned</dt>
                    <dd>{{ $job->assignee?->name ?? 'Unassigned' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Customer</dt>
                    <dd><a href="{{ route('customers.show', $job->customer) }}">{{ $job->customer->name }}</a></dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Phone</dt>
                    <dd>{{ $job->customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-label text-ink/70">Address</dt>
                    <dd>{{ $job->customer->address ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>
        <x-card class="lg:col-span-2" title="Notes">
            <p class="text-body text-ink/70 whitespace-pre-wrap">{{ $job->notes ?: 'No notes.' }}</p>
        </x-card>
    </div>

    <x-card title="Photos" class="mt-6">
        @if ($job->photos->isEmpty())
            <x-empty title="No photos yet" body="Before and after shots you add here show in the client portal." />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($job->photos as $photo)
                    <x-jobs::photo-thumb :photo="$photo">
                        <p class="mt-1 text-label text-ink/55">{{ $photo->uploader?->name ?? 'Unknown' }} · {{ $photo->created_at->diffForHumans() }}</p>
                        @can('managePhotos', $job)
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <form method="POST" action="{{ route('jobs.photos.update', [$job, $photo]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="client_visible" value="{{ $photo->client_visible ? 0 : 1 }}">
                                    <x-button type="submit" variant="ghost" size="sm">
                                        <i data-lucide="{{ $photo->client_visible ? 'eye' : 'eye-off' }}" class="size-4"></i>
                                        {{ $photo->client_visible ? 'Visible to client' : 'Hidden from client' }}
                                    </x-button>
                                </form>
                                <x-button type="button" variant="ghost" size="sm" x-on:click="$dispatch('open-modal', 'delete-photo-{{ $photo->id }}')" aria-label="Delete photo">
                                    <i data-lucide="trash-2" class="size-4"></i>
                                </x-button>
                            </div>
                            <x-modal name="delete-photo-{{ $photo->id }}" title="Delete this photo?">
                                <p>The photo and its thumbnail are removed for good.</p>
                                <x-slot:footer>
                                    <form method="POST" action="{{ route('jobs.photos.destroy', [$job, $photo]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit">Delete</x-button>
                                    </form>
                                    <x-button type="button" variant="secondary" x-on:click="open = false">Keep it</x-button>
                                </x-slot:footer>
                            </x-modal>
                        @endcan
                    </x-jobs::photo-thumb>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card title="Conversation" class="mt-6">
        @forelse ($job->comments as $comment)
            <div class="border-b border-line-soft py-3 last:border-0">
                <div class="text-micro uppercase text-ink/45">{{ $comment->user?->name ?? 'Unknown' }} · {{ $comment->created_at->diffForHumans() }}</div>
                <p class="mt-1 text-body whitespace-pre-wrap">{{ $comment->body }}</p>
            </div>
        @empty
            <p class="text-control text-ink/55">No messages yet.</p>
        @endforelse

        @can('comment', $job)
            <form method="POST" action="{{ route('jobs.comments.store', $job) }}" class="mt-4 border-t-2 border-line pt-4">
                @csrf
                <x-field label="Reply to the client" for="comment_body" :error="$errors->first('body')">
                    <x-textarea id="comment_body" name="body" rows="3" required :error="$errors->has('body')">{{ old('body') }}</x-textarea>
                </x-field>
                <div class="mt-3">
                    <x-button type="submit">
                        <i data-lucide="send" class="size-4"></i>
                        Post reply
                    </x-button>
                </div>
            </form>
        @endcan
    </x-card>
@endsection
