<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shopmade\Jobs\Models\Job;

class JobScheduleController extends Controller
{
    public function __invoke(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $data = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_at'],
        ]);

        $job->update([
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'scheduled_end_at' => ($data['scheduled_at'] ?? null) ? ($data['scheduled_end_at'] ?? null) : null,
        ]);

        return redirect()->route('jobs.show', $job)->with('status', $job->scheduled_at ? 'Job rescheduled.' : 'Job unscheduled.');
    }
}
