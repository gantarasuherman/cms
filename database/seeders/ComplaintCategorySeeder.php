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
            /*
             * Tidak ada kategori "Pertanyaan" tersendiri.
             *
             * Jenis-jenis di atas mencakup pertanyaan sekaligus pengaduan:
             * "kapan jembatan itu diperbaiki" adalah urusan orang yang sama
             * dengan "jembatan itu rusak". Sebuah keranjang terpisah membuat
             * pertanyaan tentang irigasi berhenti di petugas yang tidak
             * mengurus irigasi, dan memaksa warga menebak apakah kalimatnya
             * terhitung pertanyaan atau pengaduan.
             */

            /*
             * Kategori yang ditambahkan belakangan ditulis di bawah sini,
             * dengan sort_order tegas — bukan disisipkan ke tengah daftar.
             *
             * Dua sebabnya, dan keduanya menggigit diam-diam:
             *
             * 1. Nomor urut ini adalah angka yang dibalas warga di chat.
             *    Menyisipkan di tengah membuat "Pengaduan Lainnya" yang
             *    kemarin dibalas 3 mendadak menjadi 4 — termasuk bagi orang
             *    yang sedang berada di tengah percakapan.
             *
             * 2. sort_order entri lain diturunkan dari posisinya di array ini,
             *    sedangkan firstOrCreate tidak memperbarui baris yang sudah
             *    ada. Menyisipkan di tengah karena itu menggeser nomor pada
             *    pemasangan baru, tetapi tidak pada pemasangan lama — dua
             *    urutan berbeda dari satu berkas yang sama.
             */
            ['name' => 'Jembatan', 'slug' => 'jembatan', 'icon' => 'layers', 'requires_photo' => true, 'requires_location' => true,
                'sort_order' => 35,
                'description' => 'Jembatan rusak, lapuk, atau tidak aman dilalui.'],
            ['name' => 'Drainase', 'slug' => 'drainase', 'icon' => 'workflow', 'requires_photo' => true, 'requires_location' => true,
                'sort_order' => 36,
                'description' => 'Saluran air kota tersumbat, meluap, atau rusak.'],
            ['name' => 'Bangunan', 'slug' => 'bangunan', 'icon' => 'building-2', 'requires_photo' => true, 'requires_location' => true,
                'sort_order' => 37,
                'description' => 'Bangunan gedung milik daerah yang rusak atau tidak aman.'],
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
