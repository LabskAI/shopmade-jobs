<?php

namespace Shopmade\Jobs;

use App\Core\Packages\Hooks;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Mcp\JobsListTool;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;
use Shopmade\Jobs\Policies\JobPolicy;

/**
 * Jobs, the weekly schedule, crew photos and job conversations, plugged into core through Hooks.
 */
class JobsServiceProvider extends ServiceProvider
{
    public function boot(Hooks $hooks): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'jobs');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'jobs');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Gate::policy(Job::class, JobPolicy::class);

        Customer::resolveRelationUsing('jobs', fn (Customer $customer) => $customer->hasMany(Job::class));

        $hooks->tool([
            'slug' => 'jobs',
            'label' => 'Jobs',
            'group' => 'Work',
            'route' => 'jobs.index',
            'pattern' => 'jobs.*',
            'view_roles' => ['super_admin', 'admin', 'user'],
            'manage_roles' => ['super_admin', 'admin', 'user'],
        ]);

        $hooks->tool([
            'slug' => 'schedule',
            'label' => 'Schedule',
            'group' => 'Work',
            'route' => 'schedule.index',
            'pattern' => 'schedule.*',
            'view_roles' => ['super_admin', 'admin', 'user'],
            'manage_roles' => ['super_admin', 'admin', 'user'],
        ]);

        $hooks->status(JobStatus::InProgress->value, like: 'running', label: JobStatus::InProgress->label());
        $hooks->status(JobStatus::Done->value, like: 'complete', label: JobStatus::Done->label());

        $hooks->dashboardCard('jobs::hooks.dashboard-card', fn (User $user): array => $this->dashboardData($user));
        $hooks->customerPanel('jobs::hooks.customer-panel', fn (Customer $customer): array => [
            'customer' => $customer,
            'jobs' => $customer->jobs()->orderByDesc('scheduled_at')->limit(10)->get(),
        ]);

        $hooks->portalTab('Visits', 'portal.jobs.index', 'portal.jobs.*', icon: 'calendar-check');
        $hooks->portalSection('jobs::hooks.portal-section', fn (User $user): array => $this->portalData($user));

        $hooks->mcpTool('jobs_list', JobsListTool::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(User $user): array
    {
        $open = [JobStatus::Scheduled, JobStatus::InProgress];

        return [
            'openJobs' => Job::query()->whereIn('status', $open)->count(),
            'myJobsToday' => $user->isUser()
                ? Job::query()
                    ->with('customer')
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', $open)
                    ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
                    ->orderBy('scheduled_at')
                    ->get()
                : null,
            'upcomingJobs' => Job::query()
                ->with(['customer', 'assignee'])
                ->whereIn('status', $open)
                ->where(fn ($query) => $query->where('scheduled_at', '>=', now()->startOfDay())->orWhereNull('scheduled_at'))
                ->orderByRaw('scheduled_at is null, scheduled_at asc')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portalData(User $user): array
    {
        $customerIds = $user->customers()->pluck('customers.id');
        $open = [JobStatus::Scheduled, JobStatus::InProgress];

        $upcoming = Job::query()
            ->with(['customer', 'assignee'])
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', $open)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();

        return [
            'nextVisit' => $upcoming->first(),
            'upcomingVisits' => $upcoming->skip(1)->values(),
            'recentPhotos' => JobPhoto::query()
                ->with('job.customer')
                ->where('client_visible', true)
                ->whereHas('job', fn ($query) => $query->whereIn('customer_id', $customerIds))
                ->latest()
                ->limit(6)
                ->get(),
            'openVisits' => Job::query()->whereIn('customer_id', $customerIds)->whereIn('status', $open)->count(),
        ];
    }
}
