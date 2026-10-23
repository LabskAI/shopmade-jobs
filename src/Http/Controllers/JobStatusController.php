<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;

class JobStatusController extends Controller
{
    public function __invoke(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $data = $request->validate([
            'status' => ['required', Rule::enum(JobStatus::class)],
        ]);

        $job->update(['status' => JobStatus::from($data['status'])]);

        return redirect()->route('jobs.show', $job)->with('status', 'Job marked '.$job->status->label().'.');
    }
}
