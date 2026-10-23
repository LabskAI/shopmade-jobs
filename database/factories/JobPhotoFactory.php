<?php

namespace Shopmade\Jobs\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Shopmade\Jobs\Enums\JobPhotoKind;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;

/**
 * @extends Factory<JobPhoto>
 */
class JobPhotoFactory extends Factory
{
    protected $model = JobPhoto::class;

    public function definition(): array
    {
        $name = fake()->lexify('??????????');

        return [
            'job_id' => Job::factory(),
            'user_id' => User::factory(),
            'path' => 'job-photos/1/'.$name.'.jpg',
            'thumb_path' => 'job-photos/1/'.$name.'-thumb.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 4_000_000),
            'kind' => fake()->randomElement(JobPhotoKind::cases()),
            'caption' => fake()->optional(0.4)->sentence(3),
            'client_visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['client_visible' => false]);
    }

    public function before(): static
    {
        return $this->state(fn () => ['kind' => JobPhotoKind::Before]);
    }

    public function after(): static
    {
        return $this->state(fn () => ['kind' => JobPhotoKind::After]);
    }
}
