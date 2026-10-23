<?php

namespace Shopmade\Jobs\Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'assigned_to' => null,
            'scheduled_at' => fake()->dateTimeBetween('-2 weeks', '+3 weeks'),
            'status' => fake()->randomElement(JobStatus::cases()),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }
}
