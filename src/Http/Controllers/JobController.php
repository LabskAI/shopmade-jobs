<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Job::class);

        $jobs = Job::query()
            ->with(['customer', 'assignee'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('q')->toString(), function ($query, string $search) {
                $query->whereHas('customer', fn ($customer) => $customer->whereRaw('lower(name) like ?', ['%'.strtolower($search).'%']));
            })
            ->orderByRaw('scheduled_at is null, scheduled_at asc')
            ->paginate(20)
            ->withQueryString();

        return view('jobs::jobs.index', [
            'jobs' => $jobs,
            'statuses' => JobStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Job::class);

        return view('jobs::jobs.create', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'staff' => $this->staff(),
            'statuses' => JobStatus::cases(),
            'selectedCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $job = Job::query()->create($this->validated($request));

        return redirect()->route('jobs.show', $job)->with('status', 'Job created.');
    }

    public function show(Job $job): View
    {
        $this->authorize('view', $job);
        abort_if(auth()->user()->isClient(), 403);

        $job->load(['customer', 'assignee', 'comments.user', 'photos.uploader']);

        return view('jobs::jobs.show', [
            'job' => $job,
        ]);
    }

    public function edit(Job $job): View
    {
        $this->authorize('update', $job);

        return view('jobs::jobs.edit', [
            'job' => $job,
            'customers' => Customer::query()->orderBy('name')->get(),
            'staff' => $this->staff(),
            'statuses' => JobStatus::cases(),
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update($this->validated($request));

        return redirect()->route('jobs.show', $job)->with('status', 'Job updated.');
    }

    public function destroy(Job $job): RedirectResponse
    {
        $this->authorize('delete', $job);

        $job->delete();

        return redirect()->route('jobs.index')->with('status', 'Job deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNot('role', UserRole::Client->value)->whereNull('deactivated_at')],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['exclude_without:scheduled_at', 'nullable', 'date', 'after:scheduled_at'],
            'status' => ['required', Rule::enum(JobStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (blank($data['scheduled_at'] ?? null)) {
            $data['scheduled_end_at'] = null;
        }

        return $data;
    }

    private function staff()
    {
        return User::query()
            ->where('role', '!=', UserRole::Client)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get();
    }
}
