<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-23 10:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows this week under its day heading and hides next week until asked', function () {
    $staff = User::factory()->user()->create();
    $thisWeek = Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Helen Park']),
        'scheduled_at' => Carbon::parse('2026-09-24 09:00:00'),
        'status' => JobStatus::Scheduled,
    ]);
    Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Owen Next']),
        'scheduled_at' => Carbon::parse('2026-10-01 09:00:00'),
        'status' => JobStatus::Scheduled,
    ]);

    $this->actingAs($staff)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertSee('Sep 21')
        ->assertSeeInOrder(['Thu, Sep 24', 'Helen Park'])
        ->assertSee('9:00am')
        ->assertSee(route('jobs.show', $thisWeek), false)
        ->assertDontSee('Owen Next');

    $this->actingAs($staff)
        ->get(route('schedule.index', ['week' => '2026-10-01']))
        ->assertOk()
        ->assertSee('Owen Next')
        ->assertDontSee('Helen Park');
});

it('filters the week by crew member', function () {
    $staff = User::factory()->user()->create();
    $maria = User::factory()->user()->create(['name' => 'Maria Crew']);
    $other = User::factory()->user()->create(['name' => 'Other Crew']);

    Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Maria Customer']),
        'assigned_to' => $maria->id,
        'scheduled_at' => Carbon::parse('2026-09-22 08:00:00'),
        'status' => JobStatus::Scheduled,
    ]);
    Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Other Customer']),
        'assigned_to' => $other->id,
        'scheduled_at' => Carbon::parse('2026-09-22 13:00:00'),
        'status' => JobStatus::Scheduled,
    ]);

    $this->actingAs($staff)
        ->get(route('schedule.index', ['crew' => $maria->id]))
        ->assertOk()
        ->assertSee('Maria Customer')
        ->assertDontSee('Other Customer');
});

it('lists open jobs without a date under Unscheduled and skips closed ones', function () {
    $staff = User::factory()->user()->create();
    Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Waiting Customer']),
        'scheduled_at' => null,
        'status' => JobStatus::Scheduled,
    ]);
    Job::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Finished Customer']),
        'scheduled_at' => null,
        'status' => JobStatus::Done,
    ]);

    $this->actingAs($staff)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertSeeInOrder(['Unscheduled', 'Waiting Customer'])
        ->assertDontSee('Finished Customer');
});

it('keeps clients and guests out of the schedule', function () {
    $this->get(route('schedule.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->client()->create())
        ->get(route('schedule.index'))
        ->assertForbidden();
});

it('links the schedule from the app nav for crew', function () {
    $this->actingAs(User::factory()->user()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('schedule.index'), false);
});
