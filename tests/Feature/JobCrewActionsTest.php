<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobComment;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

it('lets crew advance a job with one tap', function () {
    $crew = User::factory()->user()->create();
    $job = Job::factory()->create(['status' => JobStatus::Scheduled, 'assigned_to' => $crew->id]);

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Start')
        ->assertDontSee('Mark done');

    $this->actingAs($crew)
        ->post(route('jobs.status.store', $job), ['status' => JobStatus::InProgress->value])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHas('status', 'Job marked In progress.');

    expect($job->fresh()->status)->toBe(JobStatus::InProgress);

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Mark done');

    $this->actingAs($crew)
        ->post(route('jobs.status.store', $job), ['status' => JobStatus::Done->value])
        ->assertRedirect(route('jobs.show', $job));

    expect($job->fresh()->status)->toBe(JobStatus::Done);

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertDontSee('Mark done')
        ->assertDontSee('Cancel job');
});

it('validates and saves a reschedule', function () {
    $crew = User::factory()->user()->create();
    $job = Job::factory()->create(['status' => JobStatus::Scheduled]);

    $this->actingAs($crew)
        ->from(route('jobs.show', $job))
        ->post(route('jobs.schedule.store', $job), [
            'scheduled_at' => '2026-09-22T09:00',
            'scheduled_end_at' => '2026-09-22T08:00',
        ])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHasErrors('scheduled_end_at');

    $this->actingAs($crew)
        ->post(route('jobs.schedule.store', $job), [
            'scheduled_at' => '2026-09-22T09:00',
            'scheduled_end_at' => '2026-09-22T12:00',
        ])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHas('status', 'Job rescheduled.');

    $job->refresh();

    expect($job->scheduled_at->format('Y-m-d H:i'))->toBe('2026-09-22 09:00')
        ->and($job->scheduled_end_at->format('Y-m-d H:i'))->toBe('2026-09-22 12:00')
        ->and($job->windowLabel())->toBe('Tue, Sep 22 · 9:00am–12:00pm');

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Tue, Sep 22 · 9:00am–12:00pm');

    $this->actingAs($crew)
        ->post(route('jobs.schedule.store', $job), ['scheduled_at' => ''])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHas('status', 'Job unscheduled.');

    $job->refresh();

    expect($job->scheduled_at)->toBeNull()
        ->and($job->scheduled_end_at)->toBeNull();
});

it('shows a staff reply on the job page and in the client portal', function () {
    $crew = User::factory()->user()->create();
    $client = User::factory()->client()->create();
    $job = Job::factory()->create();
    $client->customers()->attach($job->customer_id);

    $this->actingAs($crew)
        ->post(route('jobs.comments.store', $job), ['body' => 'We will be there at nine.'])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHas('status', 'Reply posted.');

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('We will be there at nine.');

    $this->actingAs($client)
        ->get(route('portal.jobs.show', $job))
        ->assertOk()
        ->assertSee('We will be there at nine.');

    $this->actingAs($client)
        ->post(route('jobs.comments.store', $job), ['body' => 'Not from here.'])
        ->assertForbidden();
});

it('keeps clients off the staff job page', function () {
    $client = User::factory()->client()->create();
    $job = Job::factory()->create();
    $client->customers()->attach($job->customer_id);

    $this->actingAs($client)
        ->get(route('jobs.show', $job))
        ->assertForbidden();

    $this->actingAs($client)
        ->post(route('jobs.status.store', $job), ['status' => JobStatus::Done->value])
        ->assertForbidden();
});

it('shows client comments to staff', function () {
    $crew = User::factory()->user()->create();
    $client = User::factory()->client()->create(['name' => 'Helen Park']);
    $job = Job::factory()->create();

    JobComment::factory()->create([
        'job_id' => $job->id,
        'user_id' => $client->id,
        'body' => 'Please use the side gate.',
    ]);

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Conversation')
        ->assertSee('Helen Park')
        ->assertSee('Please use the side gate.');
});
