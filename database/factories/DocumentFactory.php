<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'description' => fake()->sentence(),
            'disk' => 'local',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'file_name' => 'dokumen.pdf',
            'file_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_active' => true,
        ];
    }
}
