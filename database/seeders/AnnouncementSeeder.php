<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Seeder;

/**
 * Example notices. Matched by title, so re-running never duplicates them and
 * never overwrites wording an administrator has since edited.
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $examples = [
            [
                'badge' => 'Info Terbaru',
                'title' => 'Layanan Perizinan Daring Telah Dibuka',
                'body' => 'Pengajuan izin kini dapat dilakukan sepenuhnya secara daring, tanpa perlu datang ke kantor. Berkas diverifikasi paling lama tiga hari kerja.',
                'code' => null,
                'button_text' => 'Lihat Cara Mengajukan',
                'link' => '/layanan',
                'ticker_text' => 'Layanan perizinan daring telah dibuka — ajukan tanpa datang ke kantor.',
                'sort_order' => 10,
            ],
            [
                'badge' => 'Pengumuman',
                'title' => 'Jadwal Pemeliharaan Sistem',
                'body' => 'Situs akan dipelihara pada Sabtu pukul 22.00–24.00 WIB. Selama rentang tersebut sebagian layanan mungkin tidak dapat diakses.',
                'button_text' => null,
                'link' => null,
                'ticker_text' => 'Pemeliharaan sistem Sabtu, 22.00–24.00 WIB.',
                'sort_order' => 20,
            ],
            [
                'badge' => 'Agenda',
                'title' => 'Konsultasi Publik Rencana Tata Ruang',
                'body' => 'Masyarakat diundang menyampaikan masukan atas rancangan tata ruang wilayah. Pendaftaran dibuka hingga kuota terpenuhi.',
                'button_text' => 'Daftar Ikut Serta',
                'link' => '/berita',
                'ticker_text' => 'Konsultasi publik tata ruang — pendaftaran dibuka.',
                'sort_order' => 30,
            ],
        ];

        foreach ($examples as $example) {
            Announcement::firstOrCreate(['title' => $example['title']], $example + ['is_active' => true]);
        }

        app(PublicCache::class)->forget(PublicCache::ANNOUNCEMENTS);

        $this->command?->info('Pengumuman: '.count($examples).' tersimpan.');
    }
}
