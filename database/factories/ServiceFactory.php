<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => str($name)->slug()->value(),
            'description' => fake()->sentence(),
            'processing_time' => fake()->randomElement(['1 hari kerja', '3 hari kerja', '7 hari kerja']),
            'status' => Service::STATUS_DRAFT,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Service::STATUS_PUBLISHED]);
    }
}
