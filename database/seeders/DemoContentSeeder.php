<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Faq;
use App\Models\News;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Seeder;

/**
 * Sample content, so a fresh install has something to look at.
 *
 * Not part of DatabaseSeeder: run it explicitly with
 *   php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::first();

        $newsCategories = collect(['Infrastruktur', 'Kegiatan', 'Pengumuman'])
            ->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(
                ['type' => Category::TYPE_NEWS, 'slug' => str($name)->slug()->value()],
                ['name' => $name, 'is_active' => true],
            )]);

        $serviceCategory = Category::firstOrCreate(
            ['type' => Category::TYPE_SERVICE, 'slug' => 'perizinan'],
            ['name' => 'Perizinan', 'is_active' => true],
        );

        $faqCategory = Category::firstOrCreate(
            ['type' => Category::TYPE_FAQ, 'slug' => 'umum'],
            ['name' => 'Umum', 'is_active' => true],
        );

        foreach ([
            ['Perbaikan Jalan Utama Rampung Lebih Cepat', 'Infrastruktur', true],
            ['Sosialisasi Layanan Perizinan Daring', 'Kegiatan', true],
            ['Jadwal Pelayanan Selama Hari Libur', 'Pengumuman', false],
            ['Peningkatan Saluran Drainase Dimulai', 'Infrastruktur', false],
            ['Pelatihan Aparatur Bidang Tata Ruang', 'Kegiatan', false],
        ] as [$title, $category, $featured]) {
            $news = News::firstOrCreate(
                ['slug' => str($title)->slug()->value()],
                [
                    'title' => $title,
                    'excerpt' => 'Ringkasan singkat mengenai '.mb_strtolower($title).'.',
                    'content' => "Isi lengkap berita mengenai {$title}.\n\nParagraf kedua menjelaskan latar belakang dan dampaknya bagi masyarakat.",
                    'author_id' => $author?->id,
                    'status' => News::STATUS_PUBLISHED,
                    'published_at' => now()->subDays(random_int(1, 30)),
                    'is_featured' => $featured,
                ],
            );

            $news->categories()->syncWithoutDetaching([$newsCategories[$category]->id]);
        }

        $services = [
            ['Izin Mendirikan Bangunan', '14 hari kerja', [
                'requirements' => [['Fotokopi KTP', true], ['Fotokopi sertifikat tanah', true], ['Gambar rencana bangunan', true], ['Surat kuasa', false]],
                'tariffs' => [['Biaya administrasi', 10000], ['Biaya pemeriksaan', 15000]],
                'steps' => ['Pengajuan berkas', 'Verifikasi administrasi', 'Peninjauan lapangan', 'Persetujuan', 'Penerbitan izin'],
            ]],
            ['Rekomendasi Pemanfaatan Ruang', '7 hari kerja', [
                'requirements' => [['Surat permohonan', true], ['Fotokopi KTP', true]],
                'tariffs' => [['Biaya administrasi', 5000]],
                'steps' => ['Pengajuan', 'Telaah teknis', 'Penerbitan rekomendasi'],
            ]],
            ['Informasi Publik', '3 hari kerja', [
                'requirements' => [['Formulir permohonan informasi', true]],
                'tariffs' => [],
                'steps' => ['Pengajuan', 'Verifikasi', 'Penyampaian informasi'],
            ]],
        ];

        foreach ($services as $index => [$name, $time, $details]) {
            $service = Service::firstOrCreate(
                ['slug' => str($name)->slug()->value()],
                [
                    'name' => $name,
                    'category_id' => $serviceCategory->id,
                    'description' => 'Layanan '.mb_strtolower($name).' bagi masyarakat.',
                    'processing_time' => $time,
                    'status' => Service::STATUS_PUBLISHED,
                    'sort_order' => ($index + 1) * 10,
                    'icon' => 'briefcase',
                ],
            );

            if ($service->requirements()->doesntExist()) {
                foreach ($details['requirements'] as $i => [$requirement, $required]) {
                    $service->requirements()->create([
                        'name' => $requirement,
                        'is_required' => $required,
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }

            if ($service->tariffs()->doesntExist()) {
                foreach ($details['tariffs'] as $i => [$tariff, $amount]) {
                    $service->tariffs()->create([
                        'name' => $tariff,
                        'amount' => $amount,
                        'is_active' => true,
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }

            if ($service->steps()->doesntExist()) {
                foreach ($details['steps'] as $i => $step) {
                    $service->steps()->create(['name' => $step, 'sort_order' => ($i + 1) * 10]);
                }
            }
        }

        foreach ([
            ['Bagaimana cara mengajukan izin mendirikan bangunan?', 'Ajukan berkas melalui loket pelayanan atau kanal daring, lalu ikuti tahapan yang tercantum pada halaman layanan terkait.'],
            ['Berapa lama proses perizinan?', 'Setiap layanan memiliki waktu penyelesaian yang berbeda dan tercantum pada halaman layanan masing-masing.'],
            ['Apakah dikenakan biaya?', 'Rincian tarif tercantum terbuka pada setiap halaman layanan. Layanan informasi publik tidak dipungut biaya.'],
            ['Bagaimana jika berkas saya ditolak?', 'Anda akan menerima pemberitahuan beserta alasannya, dan dapat mengajukan kembali setelah melengkapi kekurangan.'],
        ] as $index => [$question, $answer]) {
            Faq::firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'category_id' => $faqCategory->id, 'sort_order' => ($index + 1) * 10, 'is_active' => true],
            );
        }

        foreach ([
            ['Profil', 'profil', "Profil singkat organisasi.\n\nVisi, misi, dan struktur organisasi dijelaskan pada halaman ini."],
            ['Kontak', 'kontak', "Hubungi kami melalui alamat, telepon, atau surel yang tercantum di bagian bawah situs."],
        ] as [$title, $slug, $content]) {
            Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'content' => $content,
                    'status' => Page::STATUS_PUBLISHED,
                    'published_at' => now(),
                ],
            );
        }

        app(PublicCache::class)->flushAll();
    }
}

