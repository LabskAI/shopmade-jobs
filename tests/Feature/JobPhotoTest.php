<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Shopmade\Jobs\Enums\JobPhotoKind;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

it('lets staff upload photos and writes a thumbnail beside each original', function () {
    Storage::fake('local');

    $crew = User::factory()->user()->create();
    $job = Job::factory()->create();

    $this->actingAs($crew)
        ->post(route('jobs.photos.store', $job), [
            'photos' => [
                UploadedFile::fake()->image('a.jpg', 1200, 900),
                UploadedFile::fake()->image('b.jpg', 1200, 900),
            ],
            'kind' => JobPhotoKind::Before->value,
            'caption' => 'Kitchen on arrival',
        ])
        ->assertRedirect(route('jobs.show', $job))
        ->assertSessionHas('status', '2 photos added.');

    expect($job->photos()->count())->toBe(2);

    $photo = $job->photos()->first();

    expect($photo->kind)->toBe(JobPhotoKind::Before)
        ->and($photo->caption)->toBe('Kitchen on arrival')
        ->and($photo->user_id)->toBe($crew->id)
        ->and($photo->client_visible)->toBeTrue()
        ->and($photo->thumb_path)->not->toBeNull();

    Storage::disk('local')->assertExists($photo->path);
    Storage::disk('local')->assertExists($photo->thumb_path);

    [$width] = getimagesizefromstring(Storage::disk('local')->get($photo->thumb_path));

    expect($width)->toBeLessThanOrEqual(640);

    $this->actingAs($crew)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Kitchen on arrival')
        ->assertSee('Visible to client');
});

it('rejects files that are not images or are too large', function () {
    Storage::fake('local');

    $crew = User::factory()->user()->create();
    $job = Job::factory()->create();

    $this->actingAs($crew)
        ->post(route('jobs.photos.store', $job), [
            'photos' => [UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('photos.0');

    $this->actingAs($crew)
        ->post(route('jobs.photos.store', $job), [
            'photos' => [UploadedFile::fake()->create('big.jpg', 9000, 'image/jpeg')],
        ])
        ->assertSessionHasErrors('photos.0');

    expect(JobPhoto::query()->count())->toBe(0);
});

it('forbids clients from uploading photos', function () {
    Storage::fake('local');

    $client = User::factory()->client()->create();
    $job = Job::factory()->create();
    $client->customers()->attach($job->customer_id);

    $this->actingAs($client)
        ->post(route('jobs.photos.store', $job), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertForbidden();
});

it('lets staff hide a photo from the client and delete it with its files', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $job = Job::factory()->create();

    $this->actingAs($admin)->post(route('jobs.photos.store', $job), [
        'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
    ]);

    $photo = $job->photos()->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('jobs.photos.update', [$job, $photo]), ['client_visible' => 0])
        ->assertRedirect(route('jobs.show', $job));

    expect($photo->fresh()->client_visible)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Hidden from client');

    $this->actingAs($admin)
        ->delete(route('jobs.photos.destroy', [$job, $photo]))
        ->assertRedirect(route('jobs.show', $job));

    Storage::disk('local')->assertMissing($photo->path);
    Storage::disk('local')->assertMissing($photo->thumb_path);
    expect(JobPhoto::query()->find($photo->id))->toBeNull();
});

it('returns 404 when a photo does not belong to the job in the URL', function () {
    $admin = User::factory()->admin()->create();
    $job = Job::factory()->create();
    $otherPhoto = JobPhoto::factory()->create();

    $this->actingAs($admin)
        ->patch(route('jobs.photos.update', [$job, $otherPhoto]), ['client_visible' => 0])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('jobs.photos.destroy', [$job, $otherPhoto]))
        ->assertNotFound();

    expect(JobPhoto::query()->find($otherPhoto->id))->not->toBeNull();
});
