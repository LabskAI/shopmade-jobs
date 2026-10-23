<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobComment;

class JobCommentController extends Controller
{
    public function __invoke(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('comment', $job);
        abort_if($request->user()->isClient(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        JobComment::query()->create([
            'job_id' => $job->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()->route('jobs.show', $job)->with('status', 'Reply posted.');
    }
}
