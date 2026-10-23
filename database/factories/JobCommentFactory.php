<?php

namespace Shopmade\Jobs\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobComment;

/**
 * @extends Factory<JobComment>
 */
class JobCommentFactory extends Factory
{
    protected $model = JobComment::class;

    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
