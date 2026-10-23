<?php

namespace Shopmade\Jobs\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobComment;

class PortalJobController extends Controller
{
    public function index(Request $request): View
    {
        $customerIds = $request->user()->customers()->pluck('customers.id');

        $jobs = Job::query()
            ->with(['customer', 'assignee'])
            ->withCount('clientPhotos')
            ->whereIn('customer_id', $customerIds)
            ->orderByRaw('scheduled_at is null, scheduled_at desc')
            ->get();

        $isUpcoming = fn (Job $job) => $job->isOpen() && ($job->scheduled_at === null || $job->scheduled_at->gte(now()->startOfDay()));

        return view('jobs::portal.jobs.index', [
            'upcoming' => $jobs->filter($isUpcoming)->sortBy(fn (Job $job) => $job->scheduled_at?->timestamp ?? PHP_INT_MAX)->values(),
            'past' => $jobs->reject($isUpcoming)->values(),
        ]);
    }

    public function show(Request $request, Job $job): View
    {
        $this->authorize('view', $job);

        $job->load(['customer', 'assignee', 'comments.user', 'clientPhotos']);

        return view('jobs::portal.jobs.show', [
            'job' => $job,
            'photoGroups' => $job->clientPhotos->groupBy(fn ($photo) => $photo->kind->value),
        ]);
    }

    public function comment(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('comment', $job);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        JobComment::query()->create([
            'job_id' => $job->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()->route('portal.jobs.show', $job)->with('status', 'Comment added.');
    }
}
