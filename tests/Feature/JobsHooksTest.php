<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

it('puts jobs on the staff dashboard, the customer page and the nav', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create(['name' => 'Harbor Dental']);
    Job::factory()->for($customer)->create(['status' => JobStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upcoming jobs · 1 open')
        ->assertSee('Harbor Dental')
        ->assertSee(route('jobs.index'), false)
        ->assertSee(route('schedule.index'), false);

    $this->actingAs($admin)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('Recent jobs')
        ->assertSee(route('jobs.create', ['customer_id' => $customer->id]), false);
});

it('shows a client their next visit and a Visits tab in the portal', function () {
    $customer = Customer::factory()->create(['name' => 'Harbor Dental']);
    Job::factory()->for($customer)->create(['status' => JobStatus::InProgress, 'scheduled_at' => now()->addHours(2)]);
    Job::factory()->create(['status' => JobStatus::Scheduled, 'scheduled_at' => now()->addHours(3)]);

    $this->actingAs(portalClient($customer))
        ->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Next visit')
        ->assertSee('In progress')
        ->assertSee('All visits · 1 open')
        ->assertSee(route('portal.jobs.index'), false);
});

it('lists jobs over MCP for a read-only token', function () {
    require_once base_path('tests/Feature/Agents/Mcp/McpHelpers.php');

    Job::factory()->for(Customer::factory()->state(['name' => 'Harbor Dental']))->create(['status' => JobStatus::Scheduled, 'scheduled_at' => '2026-11-03 09:00:00']);
    Job::factory()->create(['status' => JobStatus::Done, 'scheduled_at' => '2026-11-04 09:00:00']);

    [$bearer, $session] = mcpDirector(['operator']);

    $result = mcpCall('jobs_list', ['status' => 'scheduled', 'from' => '2026-11-01'], $bearer, $session)
        ->assertOk()
        ->json('result.structuredContent');

    expect($result['jobs'])->toHaveCount(1)
        ->and($result['jobs'][0]['customer'])->toBe('Harbor Dental')
        ->and($result['jobs'][0]['status'])->toBe('scheduled');

    mcpCall('jobs_list', ['from' => 'next week'], $bearer, $session)
        ->assertOk()
        ->assertJsonPath('error.code', -32602);
});
