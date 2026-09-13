<?php

namespace Database\Seeders;

use App\Models\ComplaintCategory;
use Illuminate\Database\Seeder;

/**
 * Starter categories. Every one is editable, and the evidence rules are data —
 * the flow reads `requires_photo` and `requires_location` rather than testing
 * the category's name, so a new category behaves correctly without code.
 */
class ComplaintCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pengaduan Jalan', 'slug' => 'jalan', 'icon' => 'map-pin', 'requires_photo' => true, 'requires_location' => true,
                'description' => 'Jalan berlubang, rusak, atau tidak layak dilalui.'],
            ['name' => 'Irigasi', 'slug' => 'irigasi', 'icon' => 'workflow', 'requires_photo' => true, 'requires_location' => true,
                'description' => 'Saluran tersumbat, bocor, atau tidak mengalir.'],
            ['name' => 'Pengaduan Lainnya', 'slug' => 'lainnya', 'icon' => 'megaphone', 'requires_photo' => false, 'requires_location' => false,
                'description' => 'Hal lain yang perlu ditindaklanjuti.'],
            // Questions with no published answer land here, so they reach a
            // person instead of ending at "saya tidak tahu".
            ['name' => 'Pertanyaan', 'slug' => 'pertanyaan', 'icon' => 'circle-help', 'requires_photo' => false, 'requires_location' => false,
                'description' => 'Pertanyaan warga yang belum terjawab oleh FAQ.'],
        ];

        foreach ($categories as $index => $category) {
            ComplaintCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category + ['sort_order' => ($index + 1) * 10, 'is_active' => true],
            );
        }

        $this->command?->info('Kategori pengaduan: '.count($categories).' tersimpan.');
    }
}
