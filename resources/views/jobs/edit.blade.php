@extends('layouts.app')

@section('title', 'Edit job')
@section('breadcrumb', 'Jobs / Edit')

@section('content')
    <x-page-header title="Edit job" kicker="Work" />
    <form method="POST" action="{{ route('jobs.update', $job) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card title="Details">
            @include('jobs::jobs._form')
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('jobs.show', $job) }}">Cancel</x-button>
            </div>
        </x-card>
    </form>
@endsection
