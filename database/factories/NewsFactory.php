<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<News> */
class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'excerpt' => fake()->paragraph(),
            'content' => fake()->paragraphs(4, true),
            'author_id' => User::factory(),
            'status' => News::STATUS_DRAFT,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => News::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }
}
