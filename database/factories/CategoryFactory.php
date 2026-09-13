<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'type' => Category::TYPE_NEWS,
            'name' => ucfirst($name),
            'slug' => str($name)->slug()->value(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
