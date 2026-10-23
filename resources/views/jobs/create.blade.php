@extends('layouts.app')

@section('title', 'New job')
@section('breadcrumb', 'Jobs / New')

@section('content')
    <x-page-header title="New job" kicker="Work" />
    <form method="POST" action="{{ route('jobs.store') }}" class="max-w-3xl">
        @csrf
        <x-card title="Details">
            @include('jobs._form')
            <div class="mt-6 flex justify-start gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('jobs.index') }}">Cancel</x-button>
            </div>
        </x-card>
    </form>
@endsection
