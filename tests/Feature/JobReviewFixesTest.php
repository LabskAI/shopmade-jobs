<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

function storedPhoto(Job $job, bool $clientVisible = true): JobPhoto
{
    $photo = JobPhoto::factory()->for($job)->create([
        'path' => "job-photos/{$job->id}/photo.jpg",
        'thumb_path' => "job-photos/{$job->id}/photo-thumb.jpg",
        'client_visible' => $clientVisible,
    ]);

    Storage::disk('local')->put($photo->path, 'original-bytes');
    Storage::disk('local')->put($photo->thumb_path, 'thumb-bytes');

    return $photo;
}

it('renders the create and edit pages', function () {
    $admin = User::factory()->admin()->create();
    $job = Job::factory()->create();

    $this->actingAs($admin)->get(route('jobs.create'))->assertOk()->assertSee('Notes (the client sees these)');
    $this->actingAs($admin)->get(route('jobs.edit', $job))->assertOk();
});

it('serves photos only to staff and to clients of the job while visible', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $job = Job::factory()->for($customer)->create();
    $visible = storedPhoto($job);
    $hidden = storedPhoto(Job::factory()->for($customer)->create(), clientVisible: false);
    $client = portalClient($customer);

    $this->actingAs(User::factory()->user()->create())->get($hidden->url())->assertOk();
    $this->actingAs($client)->get($visible->thumbUrl())->assertOk();
    $this->actingAs($client)->get($hidden->url())->assertForbidden();
    $this->actingAs(portalClient(Customer::factory()->create()))->get($visible->url())->assertForbidden();
    auth()->logout();
    $this->get($visible->url())->assertRedirect(route('login'));

    expect($visible->url())->not->toContain('/storage/');
});

it('deletes photo files with the job and with the customer', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $job = Job::factory()->create();
    $photo = storedPhoto($job);

    $this->actingAs($admin)->delete(route('jobs.destroy', $job))->assertRedirect(route('jobs.index'));
    Storage::disk('local')->assertMissing([$photo->path, $photo->thumb_path]);

    $customer = Customer::factory()->create();
    $other = storedPhoto(Job::factory()->for($customer)->create());
    $customer->delete();
    Storage::disk('local')->assertMissing([$other->path, $other->thumb_path]);
});

it('unschedules a job when only the start is cleared', function () {
    $admin = User::factory()->admin()->create();
    $job = Job::factory()->create(['scheduled_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHour()]);

    $this->actingAs($admin)
        ->post(route('jobs.schedule.store', $job), ['scheduled_at' => '', 'scheduled_end_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i')])
        ->assertSessionHasNoErrors();

    expect($job->refresh()->scheduled_at)->toBeNull()->and($job->scheduled_end_at)->toBeNull();
});

it('only assigns jobs to staff', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    $payload = fn (int $assignee) => [
        'customer_id' => $customer->id,
        'assigned_to' => $assignee,
        'status' => JobStatus::Scheduled->value,
    ];

    $this->actingAs($admin)->post(route('jobs.store'), $payload(portalClient($customer)->id))->assertSessionHasErrors('assigned_to');
    $this->actingAs($admin)->post(route('jobs.store'), $payload(User::factory()->user()->create()->id))->assertSessionHasNoErrors();
});
