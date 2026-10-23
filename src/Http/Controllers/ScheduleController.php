<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Shopmade\Jobs\Models\Job;

class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Job::class);

        $week = $request->string('week')->toString();
        $start = ($week ? Carbon::parse($week) : now())->startOfWeek();
        $end = $start->copy()->endOfWeek();
        $crewId = $request->integer('crew') ?: null;

        $jobs = Job::query()
            ->with(['customer', 'assignee'])
            ->when($crewId, fn ($query) => $query->where('assigned_to', $crewId))
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('scheduled_at', [$start, $end])->orWhereNull('scheduled_at');
            })
            ->orderByRaw('scheduled_at is null, scheduled_at asc')
            ->get();

        $days = collect(range(0, 6))->mapWithKeys(function (int $offset) use ($start, $jobs) {
            $day = $start->copy()->addDays($offset);

            return [$day->toDateString() => [
                'date' => $day,
                'jobs' => $jobs->filter(fn (Job $job) => $job->scheduled_at?->isSameDay($day))->values(),
            ]];
        });

        return view('jobs::schedule.index', [
            'weekStart' => $start,
            'weekEnd' => $end,
            'previousWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'days' => $days,
            'unscheduled' => $jobs->whereNull('scheduled_at')->filter(fn (Job $job) => $job->isOpen())->values(),
            'crew' => User::query()->whereIn('role', ['super_admin', 'admin', 'user'])->orderBy('name')->get(),
            'crewId' => $crewId,
        ]);
    }
}
