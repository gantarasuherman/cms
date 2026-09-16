<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Faq;
use App\Models\News;
use App\Models\Page;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cache\PublicCache;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;

/**
 * Contoh isi situs untuk Dinas PUPR Kabupaten Indramayu.
 *
 * Ketujuh layanan beserta persyaratan, tahapan, dan tarifnya disalin dari
 * dokumen "Alur Pelayanan 2026". Berita, FAQ, dan halaman statis di bawahnya
 * adalah CONTOH yang ditulis untuk mengisi tampilan — bukan laporan atas
 * peristiwa yang benar-benar terjadi. Ganti sebelum situs dipakai sungguhan.
 *
 * Bukan bagian dari DatabaseSeeder. Jalankan sendiri:
 *   php artisan db:seed --class=PuprIndramayuSeeder
 *
 * Aman diulang: seluruhnya firstOrCreate, dan baris anak hanya ditulis bila
 * layanannya belum punya.
 */
class PuprIndramayuSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedServices();
        $this->seedNews();
        $this->seedFaqs();
        $this->seedPages();

        app(SettingService::class)->forget();
        app(PublicCache::class)->flushAll();

        $this->command?->info('Contoh isi PUPR Indramayu tersimpan.');
    }

    /**
     * Identitas situs, hanya bila masih bawaan.
     *
     * site_name menyetir judul halaman, kepala footer, meta SEO, sidebar
     * admin, dan variabel {{site_name}} yang dipakai balasan chatbot — jadi
     * isinya tidak boleh ditulis ulang di tiap tempat. Nilainya milik
     * administrator dan dapat diubah kapan saja dari /admin/settings/general;
     * karena itu seeder ini hanya mengganti nilai yang masih persis sama
     * dengan bawaan SettingSeeder. Sekali seseorang mengubahnya dari panel,
     * menjalankan ulang seeder ini tidak akan menimpanya.
     */
    private function seedSettings(): void
    {
        $name = 'Dinas PUPR Kabupaten Indramayu';
        $description = 'Portal informasi dan layanan Dinas Pekerjaan Umum dan Penataan Ruang Kabupaten Indramayu.';

        // [grup => [kunci => [nilai bawaan yang boleh diganti, nilai baru]]]
        $identity = [
            'general' => [
                'site_name' => ['Dynamic CMS', $name],
                'site_description' => ['Portal informasi dan layanan publik.', $description],
                // Satu-satunya nomor umum yang tercantum di dokumen alur pelayanan.
                'phone' => [null, '(0234) 274264'],
                // Dokumen hanya memuat surel per bidang, tidak ada alamat umum.
                // Mengosongkan alamat contoh lebih jujur daripada membiarkan
                // info@example.test tampil di footer situs instansi sungguhan.
                'email' => ['info@example.test', null],
                // Dikosongkan agar footer menyusunnya sendiri dari tahun
                // berjalan dan site_name, sehingga ikut berubah bila nama
                // situs diubah dari panel.
                'copyright' => ['© '.date('Y').' Dynamic CMS. Hak cipta dilindungi.', null],
            ],
            // Judul beranda diambil dari seo_title lebih dulu; site_name hanya
            // cadangan bila seo_title kosong. Tanpa baris ini beranda tetap
            // menyebut nama bawaan meski nama situs sudah diganti.
            'seo' => [
                'seo_title' => ['Dynamic CMS', $name],
                'seo_description' => ['Portal informasi dan layanan publik.', $description],
                'seo_keywords' => [
                    'cms, informasi publik, layanan',
                    'pupr indramayu, perizinan, tata ruang, peil banjir, sewa alat berat',
                ],
            ],
        ];

        foreach ($identity as $group => $entries) {
            foreach ($entries as $key => [$shipped, $value]) {
                $setting = Setting::where('group', $group)->where('key', $key)->first();

                if (! $setting) {
                    continue;
                }

                $current = $setting->value;
                $untouched = $current === $shipped || $current === null || $current === '';

                if ($untouched) {
                    $setting->update(['value' => $value]);
                }
            }
        }
    }

    /**
     * Tarif tidak ditulis sebagai baris service_tariffs.
     *
     * Tabel tarif pada halaman layanan menjumlahkan barisnya menjadi "Total" —
     * benar untuk komponen biaya yang saling menambah, keliru untuk kartu
     * tarif seperti pada dokumen ini, yang pilihannya saling menggantikan:
     * satu jenis tanah, satu zona mobilisasi, satu jenis pengujian.
     * Menjumlahkan Rp 200 tanah darat dengan Rp 600 tanah sawah tidak berarti
     * apa pun, jadi kartu tarifnya ditulis apa adanya di `content`.
     */
    private function seedServices(): void
    {
        $categories = collect([
            'Tata Bangunan' => 'tata-bangunan',
            'Sumber Daya Air' => 'sumber-daya-air',
            'Tata Ruang' => 'tata-ruang',
            'Peralatan dan Laboratorium' => 'peralatan-dan-laboratorium',
        ])->mapWithKeys(fn (string $slug, string $name) => [$name => Category::firstOrCreate(
            ['type' => Category::TYPE_SERVICE, 'slug' => $slug],
            ['name' => $name, 'is_active' => true],
        )]);

        foreach ($this->services() as $index => $service) {
            $model = Service::firstOrCreate(
                ['slug' => str($service['name'])->slug()->value()],
                [
                    'name' => $service['name'],
                    'category_id' => $categories[$service['category']]->id,
                    'description' => $service['description'],
                    'content' => $service['content'],
                    'processing_time' => $service['processing_time'],
                    'icon' => $service['icon'],
                    'status' => Service::STATUS_PUBLISHED,
                    'sort_order' => ($index + 1) * 10,
                ],
            );

            if ($model->requirements()->doesntExist()) {
                foreach ($service['requirements'] as $i => $requirement) {
                    $model->requirements()->create([
                        'name' => $requirement[0],
                        'description' => $requirement[1] ?: null,
                        'is_required' => $requirement[2] ?? true,
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }

            if ($model->steps()->doesntExist()) {
                foreach ($service['steps'] as $i => [$actor, $action]) {
                    $model->steps()->create([
                        'name' => $actor,
                        'description' => $action,
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function services(): array
    {
        return [
            [
                'name' => 'Rekomendasi Teknis Bangunan Gedung',
                'category' => 'Tata Bangunan',
                'icon' => 'building-2',
                'processing_time' => '5 Hari Kerja',
                'description' => 'Kajian gambar teknis bangunan gedung sebagai dasar pengajuan IMB, ditandatangani Kepala Dinas PUPR.',
                'content' => "Produk layanan\nAdvice Teknis Bangunan Gedung, berupa rekomendasi hasil kajian gambar teknis yang ditandatangani Kepala Dinas PUPR.\n\nBiaya\nGRATIS. Layanan ini tidak dipungut biaya dalam bentuk apa pun.\n\nUnit pelaksana\nBidang Tata Bangunan, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Konsultasi langsung di kantor Dinas PUPR\n- Email: tatabangunan66@gmail.com",
                'requirements' => [
                    ['Formulir permohonan IMB dari Dinas PMPTSP', 'Fotokopi formulir yang telah diterbitkan DPMPTSP.'],
                    ['Akte Tanah', 'Sertifikat, akte, kitir, atau bukti kepemilikan lain yang sah.'],
                    ['Fotokopi KTP pemilik atau pemohon', ''],
                    ['Gambar Teknis Bangunan', ''],
                    ['Perhitungan struktur dan hasil tes sondir', 'Wajib untuk bangunan lebih dari 3 lantai, dikeluarkan laboratorium.', false],
                ],
                'steps' => [
                    ['Pemohon', 'Mengajukan permohonan IMB ke DPMPTSP.'],
                    ['DPMPTSP', 'Meneruskan permohonan ke Dinas PUPR beserta lampiran formulir.'],
                    ['Seksi Pemanfaatan Bangunan', 'Memeriksa kelengkapan berkas permohonan.'],
                    ['Pemeriksaan gambar (KDB dan KLB)', 'Gambar yang tidak sesuai dikembalikan kepada pemohon untuk diperbaiki.'],
                    ['Seksi Pemanfaatan Bangunan', 'Melegalisasi kelengkapan gambar teknis.'],
                    ['Pembuatan gambar teknis', 'Gambar hasil kajian menjadi acuan pengajuan IMB.'],
                    ['Kepala Dinas PUPR', 'Menandatangani Rekomendasi Kajian Gambar Teknis.'],
                ],
            ],
            [
                'name' => 'Ijin Penggunaan Tanah Negara',
                'category' => 'Sumber Daya Air',
                'icon' => 'key-round',
                'processing_time' => '2 Minggu',
                'description' => 'Surat keputusan izin penggunaan tanah negara di wilayah kerja UPT PSDA, berlaku dua tahun.',
                'content' => "Produk layanan\nSK Ijin Penggunaan Tanah Negara, berlaku 2 tahun dan diperpanjang melalui UPT PSDA setempat.\n\nBiaya\nSesuai tarif retribusi. Retribusi dibayarkan setiap tahun melalui Bank BJB dan diterima Kas Daerah.\n\nTarif retribusi infrastruktur\n- Usaha: Rp 30.000 per m2 per tahun\n- Non usaha: Rp 11.250 per m2 per tahun\n\nTarif menurut jenis tanah\n- Tanah darat: Rp 200 per m2 per tahun\n- Tanah sawah: Rp 600 per m2 per tahun\n- Tadah hujan: Rp 350 per m2 per tahun\n- Tambak: Rp 350 per m2 per tahun\n- Kebun: Rp 200 per m2 per tahun\n\nTarif di atas adalah pilihan yang berlaku menurut peruntukan dan jenis tanah, bukan biaya yang dijumlahkan.\n\nUnit pelaksana\nUPT PSDA, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Dinas PUPR: (0234) 274264\n- Staf O&P Perijinan: Sumajaya, 089630476504",
                'requirements' => [
                    ['Mengisi formulir melalui UPT PSDA setempat', ''],
                    ['Fotokopi KTP', 'Untuk pemohon perorangan.', false],
                    ['Akte pendirian perusahaan', 'Untuk pemohon berbentuk badan usaha.', false],
                    ['Rekomendasi Kepala Desa atau Kelurahan', 'Diketahui Camat, menerangkan tanah tidak dalam sengketa.'],
                    ['Rekomendasi Kepala UPT PSDA setempat', ''],
                    ['Surat pernyataan penyerahan kembali', 'Menyatakan kesediaan menyerahkan tanah tanpa tuntutan ganti rugi bila diperlukan untuk kepentingan negara.'],
                ],
                'steps' => [
                    ['Pemohon', 'Membawa berkas persyaratan ke UPT PSDA setempat.'],
                    ['UPT PSDA', 'Meneruskan persyaratan ke Dinas PUPR Kabupaten Indramayu.'],
                    ['Dinas PUPR', 'Membuat SK perijinan berlaku 2 tahun, lalu mengembalikannya ke UPT PSDA.'],
                    ['UPT PSDA', 'Menyampaikan SK kepada pemohon.'],
                    ['Pemohon', 'Membayar retribusi setiap tahun melalui Bank BJB.'],
                    ['Kas Daerah', 'Menerima dan mencatat retribusi.'],
                ],
            ],
            [
                'name' => 'Arahan Teknis Peil Banjir',
                'category' => 'Sumber Daya Air',
                'icon' => 'triangle-alert',
                'processing_time' => '3 Hari Kerja',
                'description' => 'Arahan ketinggian elevasi bangunan terhadap risiko banjir, disusun dari data waterpass lokasi.',
                'content' => "Produk layanan\nDokumen Arahan Teknis Peil Banjir yang ditandatangani pejabat berwenang.\n\nBiaya\nGRATIS. Layanan ini tidak dipungut biaya dalam bentuk apa pun.\n\nUnit pelaksana\nBidang Sumber Daya Air, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Telepon: 0859 5024 2999\n- Email: peilbanjir.indramayu@gmail.com\n- Konsultasi langsung di kantor Dinas PUPR",
                'requirements' => [
                    ['Surat permohonan arahan peil banjir', ''],
                    ['Fotokopi KTP pemohon', ''],
                    ['Surat persetujuan pemanfaatan ruang', ''],
                    ['Data lokasi tanah', ''],
                    ['Gambar siteplan atau data ukur tanah', ''],
                    ['Data pengukuran ketinggian elevasi tanah', 'Hasil pengukuran waterpass pada lokasi yang dimohonkan.'],
                ],
                'steps' => [
                    ['Pemohon', 'Mengajukan surat permohonan arahan peil banjir.'],
                    ['Petugas', 'Menerima surat masuk beserta berkas permohonan.'],
                    ['Penelitian berkas', 'Memeriksa kelengkapan; berkas yang tidak lengkap dikembalikan.'],
                    ['Pemeriksaan waterpass', 'Memverifikasi data pengukuran ketinggian elevasi tanah.'],
                    ['Pembuatan arahan teknis', 'Menyusun dokumen arahan teknis peil banjir.'],
                    ['Pejabat berwenang', 'Menandatangani arahan teknis peil banjir.'],
                    ['Pemohon', 'Menerima penyerahan Arahan Teknis Peil Banjir.'],
                ],
            ],
            [
                'name' => 'Sewa Alat Berat',
                'category' => 'Peralatan dan Laboratorium',
                'icon' => 'settings',
                'processing_time' => '120 Menit (administrasi)',
                'description' => 'Penyewaan alat berat milik daerah melalui UPT Peralatan dan Perbengkelan, termasuk mobilisasi ke lokasi.',
                'content' => "Produk layanan\nSewa alat berat beserta mobilisasi dan demobilisasi ke lokasi pekerjaan.\n\nBiaya\nSesuai tarif retribusi menurut Perda Nomor 3 Tahun 2012. Yang dibayarkan adalah retribusi sewa ditambah biaya mobilisasi dan demobilisasi satu zona.\n\nMobilisasi dan demobilisasi\n- Zona I: Rp 1.800.000\n- Zona II: Rp 3.050.000\n- Zona III: Rp 4.200.000\n\nSewa alat\n- Stoom Walls 2,5 ton: Rp 200.000 per hari\n- Stoom Walls 3 ton: Rp 350.000 per hari\n- Stoom Walls 4 ton vibro: Rp 350.000 per hari\n- Stoom Walls 4 ton non-vibro: Rp 300.000 per hari\n- Stoom Walls 6-8 ton: Rp 250.000 per hari\n- Stoom Walls 8-10 ton: Rp 300.000 per hari\n- Stoom Walls 10-12 ton: Rp 350.000 per hari\n- Dumptruck: Rp 300.000 per jam\n- Excavator PC 45, PC 130, PC 200: Rp 840.000 per hari\n- Stone Crusher: Rp 35.088.000\n\nUnit pelaksana\nUPT Peralatan dan Perbengkelan, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Konsultasi langsung di UPT Peralatan dan Perbengkelan\n- Telepon: 081324539882",
                'requirements' => [
                    ['Surat permohonan sewa alat berat', ''],
                    ['Formulir data permohonan', 'Memuat identitas pemohon, jenis dan kapasitas alat, lokasi pekerjaan, lama sewa, serta pemilik pekerjaan.'],
                ],
                'steps' => [
                    ['Pemohon', 'Datang ke UPT Peralatan dan Perbengkelan untuk mengajukan permohonan.'],
                    ['Petugas operasional', 'Menerima data identitas, jenis alat, lokasi, dan lama sewa.'],
                    ['Petugas operasional', 'Mengalokasikan alat dan menentukan waktu ketersediaannya.'],
                    ['Pemohon', 'Membayar retribusi sewa beserta biaya mobilisasi dan demobilisasi.'],
                    ['UPT Peralatan dan Perbengkelan', 'Mengirim alat berat ke lokasi sesuai jadwal.'],
                    ['Pemohon', 'Menggunakan alat sesuai masa sewa yang ditentukan.'],
                    ['UPT Peralatan dan Perbengkelan', 'Melakukan demobilisasi alat berat kembali ke bengkel.'],
                ],
            ],
            [
                'name' => 'Kesesuaian Kegiatan Pemanfaatan Ruang (KKPR)',
                'category' => 'Tata Ruang',
                'icon' => 'map-pin',
                'processing_time' => '30 Hari Kerja',
                'description' => 'Persetujuan kesesuaian rencana kegiatan terhadap rencana tata ruang, diterbitkan melalui sistem OSS.',
                'content' => "Produk layanan\nPersetujuan Kesesuaian Kegiatan Pemanfaatan Ruang (PKKPR), diterbitkan melalui sistem OSS.\n\nBiaya\nSesuai PNBP yang ditetapkan sistem OSS. Pembayaran disetor langsung melalui OSS.\n\nDasar hukum\nUndang-Undang Nomor 26 Tahun 2007 dan Peraturan Pemerintah Nomor 21 Tahun 2021.\n\nUnit pelaksana\nBidang Tata Ruang, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Konsultasi langsung di Mal Pelayanan Publik (MPP)\n- Chat daring: 082120136353 (Admin Tata Ruang)",
                'requirements' => [
                    ['Akun OSS beserta kelengkapan berkas', 'Poligon lokasi, rencana teknis bangunan atau siteplan, dan KBLI yang sesuai dengan kegiatan.'],
                    ['Surat Permohonan Informasi Rencana Tata Ruang', 'Ditujukan kepada Kepala Dinas PUPR Kabupaten Indramayu.'],
                ],
                'steps' => [
                    ['Pemohon melalui OSS', 'Membuat akun OSS, mengunggah poligon, memilih KBLI, dan mengunggah siteplan.'],
                    ['Petugas verifikasi', 'Memeriksa dokumen pada Gistaru-KKPR; berkas tidak lengkap dikembalikan.'],
                    ['Pemohon', 'Menyetor PNBP melalui OSS, membuat PTP di BPN, dan mengikuti survei lokasi bila diperlukan.'],
                    ['BPN dan FPRD', 'BPN mengunggah PTP, dilanjutkan penilaian oleh Forum Penataan Ruang Daerah.'],
                    ['Bidang Tata Ruang', 'Menyusun Berita Acara FPRD dan mengunggahnya ke Gistaru-KKPR.'],
                    ['DPMPTSP melalui OSS', 'Menerbitkan PKKPR melalui sistem OSS.'],
                ],
            ],
            [
                'name' => 'Permohonan Informasi Ruang',
                'category' => 'Tata Ruang',
                'icon' => 'file-text',
                'processing_time' => '5 Hari Kerja',
                'description' => 'Surat keterangan rencana tata ruang atas suatu lokasi, mengacu pada RTRW Kabupaten Indramayu 2024-2044.',
                'content' => "Produk layanan\nSurat Informasi Rencana Tata Ruang, mengacu pada RTRW Kabupaten Indramayu 2024-2044.\n\nBiaya\nGRATIS. Layanan ini tidak dipungut biaya dalam bentuk apa pun.\n\nUnit pelaksana\nBidang Tata Ruang, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Konsultasi langsung di Mal Pelayanan Publik (MPP)\n- Chat daring: 082120136353 (Admin Tata Ruang)",
                'requirements' => [
                    ['Surat Permohonan Informasi Ruang', 'Ditujukan kepada Kepala Dinas PUPR Kabupaten Indramayu.'],
                    ['Data lokasi dan rencana kegiatan', 'Poligon, koordinat, rencana kegiatan, legalitas pemohon, serta KKPR eksisting bila ada.'],
                    ['Fotokopi surat tanah', 'Menerangkan status tanah pada lokasi yang dimohonkan.'],
                ],
                'steps' => [
                    ['Pemohon', 'Mengajukan Surat Permohonan Informasi Ruang kepada Kepala Dinas PUPR.'],
                    ['Kepala Bidang Tata Ruang', 'Menerima disposisi dan menganalisa koordinat bersama staf.'],
                    ['Staf Tata Ruang', 'Menyusun informasi berdasarkan RTRW dan membuat konsep surat balasan.'],
                    ['Paraf berjenjang', 'Kepala Bidang, Sekretaris Dinas, lalu Kepala Dinas PUPR.'],
                    ['Kepala Dinas PUPR', 'Menandatangani surat balasan informasi ruang.'],
                    ['Pemohon', 'Mengambil surat, atau menerima pemberitahuan bahwa surat telah selesai.'],
                ],
            ],
            [
                'name' => 'Sewa Alat Laboratorium Bahan Konstruksi',
                'category' => 'Peralatan dan Laboratorium',
                'icon' => 'clipboard-list',
                'processing_time' => '7 Hari Kerja',
                'description' => 'Pengujian bahan konstruksi di laboratorium maupun di lapangan oleh UPTD Laboratorium Bahan Konstruksi.',
                'content' => "Produk layanan\nPelayanan sewa alat dan pengujian bahan konstruksi, di laboratorium maupun di lapangan.\n\nWaktu penyelesaian\nBerkas lengkap diselesaikan dalam 7 hari kerja. Berkas yang belum lengkap diberitahukan secara tertulis dalam 3 hari kerja.\n\nBiaya\nSesuai tarif retribusi menurut Perda Nomor 1 Tahun 2025. Tarif di bawah berlaku per sampel atau per titik pengujian, bukan biaya yang dijumlahkan.\n\nPengujian laboratorium, per sampel\n- JMF Burda, Lapen, atau Buras: Rp 1.000.000\n- JMF LPB atau LPA: Rp 3.000.000\n- JMF Beton: Rp 2.500.000\n- Uji aspal core drill: Rp 1.500.000\n- Uji tekan beton: Rp 50.000\n\nPengujian lapangan, per titik\n- Sand Cone Test: Rp 100.000\n- Core Drill HRS: Rp 125.000\n- Core Drill Beton: Rp 250.000\n- CBR Mechanical Jack: Rp 225.000\n- DCP: Rp 145.000\n- Sondir: Rp 1.600.000 per titik per hari\n- Hand Bor: Rp 2.250.000\n\nUnit pelaksana\nUPTD Laboratorium Bahan Konstruksi, Dinas PUPR Kabupaten Indramayu.\n\nPengaduan dan masukan\n- Konsultasi langsung di UPTD Laboratorium Bahan Konstruksi",
                'requirements' => [
                    ['Mengisi formulir permohonan', ''],
                    ['Fotokopi KTP', 'Untuk pemohon perorangan.', false],
                    ['Fotokopi akta pendirian perusahaan', 'Untuk pemohon berbentuk badan usaha.', false],
                    ['Fotokopi surat izin lama dan bukti pembayaran retribusi', 'Untuk permohonan perpanjangan.', false],
                    ['Rekomendasi dari UPT setempat', ''],
                    ['Surat pernyataan pengembalian alat', 'Menyatakan kesediaan mengembalikan alat dalam kondisi baik dan memberi ganti rugi bila rusak atau hilang.'],
                    ['Bukti pembayaran retribusi', ''],
                ],
                'steps' => [
                    ['Pemohon', 'Mengajukan surat permohonan kepada Kepala UPTD Laboratorium.'],
                    ['Staf Tata Usaha', 'Mengagendakan surat dan meneruskannya ke Kepala Dinas sebagai tembusan.'],
                    ['Kepala Dinas', 'Memberi petunjuk dan persetujuan melalui lembar disposisi.'],
                    ['Kepala UPTD', 'Menyiapkan personil, bahan, dan peralatan pengujian.'],
                    ['Staf pelaksana', 'Melaksanakan pengujian sesuai permohonan.'],
                    ['Kepala UPTD', 'Membuat surat perintah sesuai permohonan, dibuat rangkap tiga.'],
                    ['Pengguna jasa', 'Membayar retribusi kepada Bendahara Penerimaan.'],
                    ['Bendahara Penerimaan', 'Menerbitkan bukti atau tanda terima pembayaran.'],
                    ['Kepala UPTD', 'Menerbitkan surat perintah tugas pelaksanaan.'],
                    ['Kasubag Keuangan', 'Melakukan pengecekan pembayaran retribusi.'],
                ],
            ],
        ];
    }

    /**
     * Berita contoh.
     *
     * Isinya karangan untuk mengisi tampilan, bukan peristiwa yang benar-benar
     * terjadi — karena itu semuanya bersandar pada apa yang memang tertulis di
     * dokumen alur pelayanan (waktu penyelesaian, kanal pengaduan, dasar
     * hukum) dan tidak menyebut angka capaian, lokasi proyek, atau nama
     * pejabat yang tidak dapat diperiksa kebenarannya.
     */
    private function seedNews(): void
    {
        $author = User::first();

        $categories = collect(['Infrastruktur', 'Kegiatan', 'Pengumuman'])
            ->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(
                ['type' => Category::TYPE_NEWS, 'slug' => str($name)->slug()->value()],
                ['name' => $name, 'is_active' => true],
            )]);

        $items = [
            [
                'Tujuh Alur Pelayanan Dinas PUPR Kini Terbuka di Situs',
                'Pengumuman',
                true,
                3,
                'Persyaratan, tahapan, waktu penyelesaian, dan tarif ketujuh layanan Dinas PUPR dapat dibaca siapa saja tanpa perlu datang lebih dulu.',
                "Seluruh alur pelayanan Dinas PUPR Kabupaten Indramayu kini dapat dibaca pada halaman Layanan di situs ini. Setiap layanan memuat persyaratan yang harus disiapkan, tahapan yang akan dilalui berkas, waktu penyelesaian, serta tarif bila ada.\n\nTujuannya sederhana: pemohon dapat memastikan kelengkapan berkas sebelum berangkat, sehingga tidak perlu bolak-balik hanya karena satu dokumen tertinggal.\n\nSetiap halaman layanan juga mencantumkan kanal pengaduan dan masukan untuk unit yang bersangkutan.",
            ],
            [
                'Tiga Layanan Dinas PUPR Tidak Dipungut Biaya',
                'Pengumuman',
                true,
                7,
                'Rekomendasi Teknis Bangunan Gedung, Arahan Teknis Peil Banjir, dan Permohonan Informasi Ruang diberikan tanpa biaya.',
                "Tiga dari tujuh layanan Dinas PUPR Kabupaten Indramayu diberikan tanpa dipungut biaya: Rekomendasi Teknis Bangunan Gedung, Arahan Teknis Peil Banjir, dan Permohonan Informasi Ruang.\n\nKetiganya tercantum GRATIS pada dokumen alur pelayanan, dan keterangan yang sama dapat dibaca pada masing-masing halaman layanan di situs ini.\n\nBila ada pungutan di luar ketentuan, masyarakat dapat menyampaikannya melalui kanal pengaduan yang tercantum pada halaman layanan terkait.",
            ],
            [
                'Arahan Teknis Peil Banjir Selesai dalam Tiga Hari Kerja',
                'Infrastruktur',
                false,
                12,
                'Layanan dengan waktu penyelesaian tersingkat di Dinas PUPR, bersandar pada data pengukuran waterpass yang dilampirkan pemohon.',
                "Arahan Teknis Peil Banjir memiliki waktu penyelesaian tersingkat di antara layanan Dinas PUPR Kabupaten Indramayu, yaitu tiga hari kerja.\n\nWaktu tersebut dihitung sejak berkas dinyatakan lengkap. Karena itu kelengkapan data pengukuran ketinggian elevasi tanah menjadi penentu: berkas yang tidak lengkap dikembalikan pada tahap penelitian berkas.\n\nPertanyaan mengenai layanan ini dapat disampaikan melalui telepon 0859 5024 2999 atau surel peilbanjir.indramayu@gmail.com.",
            ],
            [
                'Mengurus KKPR Dimulai dari Akun OSS',
                'Kegiatan',
                false,
                18,
                'Persetujuan Kesesuaian Kegiatan Pemanfaatan Ruang diterbitkan lewat sistem OSS, dengan penilaian Forum Penataan Ruang Daerah.',
                "Permohonan Kesesuaian Kegiatan Pemanfaatan Ruang (KKPR) diajukan melalui sistem OSS. Pemohon membuat akun, mengunggah poligon lokasi dan siteplan, serta memilih KBLI yang sesuai dengan rencana kegiatan.\n\nBerkas kemudian diperiksa pada Gistaru-KKPR. Setelah PNBP disetor dan PTP diterbitkan BPN, penilaian dilakukan oleh Forum Penataan Ruang Daerah sebelum PKKPR diterbitkan melalui OSS.\n\nLayanan ini berdasar pada Undang-Undang Nomor 26 Tahun 2007 dan Peraturan Pemerintah Nomor 21 Tahun 2021, dengan waktu penyelesaian 30 hari kerja.",
            ],
            [
                'Tarif Sewa Alat Berat Mengacu Perda Nomor 3 Tahun 2012',
                'Infrastruktur',
                false,
                25,
                'Biaya yang dibayarkan terdiri atas retribusi sewa alat ditambah mobilisasi dan demobilisasi menurut zona lokasi pekerjaan.',
                "Penyewaan alat berat pada UPT Peralatan dan Perbengkelan dikenakan retribusi menurut Peraturan Daerah Nomor 3 Tahun 2012.\n\nBiaya yang dibayarkan terdiri atas dua bagian: retribusi sewa alat menurut jenis dan kapasitasnya, ditambah biaya mobilisasi dan demobilisasi yang besarnya mengikuti zona lokasi pekerjaan.\n\nRincian lengkap kedua komponen tersebut tercantum pada halaman layanan Sewa Alat Berat.",
            ],
            [
                'Pengujian Bahan Konstruksi Dilayani di Laboratorium dan di Lapangan',
                'Kegiatan',
                false,
                31,
                'UPTD Laboratorium Bahan Konstruksi melayani pengujian per sampel maupun per titik, dengan tarif menurut Perda Nomor 1 Tahun 2025.',
                "UPTD Laboratorium Bahan Konstruksi Dinas PUPR Kabupaten Indramayu melayani pengujian bahan konstruksi, baik di laboratorium maupun langsung di lapangan.\n\nPengujian laboratorium dihitung per sampel, sementara pengujian lapangan seperti Sand Cone Test, Core Drill, CBR, DCP, dan Sondir dihitung per titik. Seluruh tarif mengacu pada Peraturan Daerah Nomor 1 Tahun 2025.\n\nPermohonan yang berkasnya lengkap diselesaikan dalam tujuh hari kerja. Berkas yang belum lengkap diberitahukan secara tertulis dalam tiga hari kerja.",
            ],
        ];

        foreach ($items as [$title, $category, $featured, $daysAgo, $excerpt, $content]) {
            $news = News::firstOrCreate(
                ['slug' => str($title)->slug()->value()],
                [
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'author_id' => $author?->id,
                    'status' => News::STATUS_PUBLISHED,
                    'published_at' => now()->subDays($daysAgo),
                    'is_featured' => $featured,
                ],
            );

            $news->categories()->syncWithoutDetaching([$categories[$category]->id]);
        }
    }

    private function seedFaqs(): void
    {
        $category = Category::firstOrCreate(
            ['type' => Category::TYPE_FAQ, 'slug' => 'pelayanan'],
            ['name' => 'Pelayanan', 'is_active' => true],
        );

        $faqs = [
            [
                'Layanan apa saja yang tersedia di Dinas PUPR Kabupaten Indramayu?',
                'Tersedia tujuh layanan: Rekomendasi Teknis Bangunan Gedung, Ijin Penggunaan Tanah Negara, Arahan Teknis Peil Banjir, Sewa Alat Berat, Kesesuaian Kegiatan Pemanfaatan Ruang (KKPR), Permohonan Informasi Ruang, serta Sewa Alat Laboratorium Bahan Konstruksi. Rinciannya dapat dibaca pada halaman Layanan.',
            ],
            [
                'Apakah semua layanan dipungut biaya?',
                'Tidak. Rekomendasi Teknis Bangunan Gedung, Arahan Teknis Peil Banjir, dan Permohonan Informasi Ruang tidak dipungut biaya. Layanan lainnya dikenakan retribusi atau PNBP yang rinciannya tercantum terbuka pada masing-masing halaman layanan.',
            ],
            [
                'Berapa lama waktu penyelesaian setiap layanan?',
                'Berbeda-beda: Arahan Teknis Peil Banjir tiga hari kerja, Rekomendasi Teknis Bangunan Gedung dan Permohonan Informasi Ruang lima hari kerja, Sewa Alat Laboratorium tujuh hari kerja, Ijin Penggunaan Tanah Negara dua minggu, dan KKPR 30 hari kerja. Sewa Alat Berat memerlukan sekitar 120 menit untuk administrasinya.',
            ],
            [
                'Bagaimana jika berkas saya belum lengkap?',
                'Berkas yang belum lengkap dikembalikan pada tahap pemeriksaan agar dapat dilengkapi, dan permohonan dapat diajukan kembali. Khusus Sewa Alat Laboratorium Bahan Konstruksi, pemberitahuan tertulis disampaikan dalam tiga hari kerja.',
            ],
            [
                'Kapan bangunan saya memerlukan perhitungan struktur dan tes sondir?',
                'Perhitungan struktur beserta hasil tes sondir dari laboratorium diwajibkan untuk bangunan lebih dari tiga lantai pada layanan Rekomendasi Teknis Bangunan Gedung.',
            ],
            [
                'Berapa lama proses izin mendirikan bangunan?',
                'Di Dinas PUPR, kajian gambar teknisnya selesai dalam lima hari kerja melalui layanan Rekomendasi Teknis Bangunan Gedung, dan tidak dipungut biaya. IMB-nya sendiri diterbitkan DPMPTSP; rekomendasi dari Dinas PUPR adalah salah satu berkas yang dipakai di sana.',
            ],
            [
                'Ke mana permohonan IMB diajukan pertama kali?',
                'Permohonan IMB diajukan ke DPMPTSP. DPMPTSP kemudian meneruskannya ke Dinas PUPR beserta lampiran formulir untuk dikaji gambar teknisnya.',
            ],
            [
                'Berapa lama SK Ijin Penggunaan Tanah Negara berlaku?',
                'SK berlaku dua tahun. Retribusinya dibayarkan setiap tahun melalui Bank BJB dan diterima Kas Daerah.',
            ],
            [
                'Ke mana saya menyampaikan pengaduan atau masukan?',
                'Setiap halaman layanan mencantumkan kanal pengaduan unit yang bersangkutan. Untuk keperluan umum, Dinas PUPR dapat dihubungi di (0234) 274264, atau melalui formulir pengaduan pada situs ini.',
            ],
        ];

        foreach ($faqs as $index => [$question, $answer]) {
            Faq::firstOrCreate(
                ['question' => $question],
                [
                    'answer' => $answer,
                    'category_id' => $category->id,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedPages(): void
    {
        $pages = [
            [
                'Profil',
                'profil',
                "Dinas Pekerjaan Umum dan Penataan Ruang (PUPR) Kabupaten Indramayu menyelenggarakan urusan pemerintahan di bidang pekerjaan umum dan penataan ruang.\n\nPelayanan kepada masyarakat dilaksanakan melalui bidang dan unit pelaksana teknis, yaitu Bidang Tata Bangunan, Bidang Sumber Daya Air, Bidang Tata Ruang, UPT PSDA, UPT Peralatan dan Perbengkelan, serta UPTD Laboratorium Bahan Konstruksi.\n\nSeluruh alur pelayanan beserta persyaratan, tahapan, waktu penyelesaian, dan tarifnya dapat dibaca pada halaman Layanan.",
            ],
            [
                'Kontak',
                'kontak',
                "Dinas Pekerjaan Umum dan Penataan Ruang Kabupaten Indramayu\n\nTelepon: (0234) 274264\n\nKanal per layanan:\n- Bidang Tata Bangunan: tatabangunan66@gmail.com\n- Bidang Sumber Daya Air (peil banjir): 0859 5024 2999, peilbanjir.indramayu@gmail.com\n- Bidang Tata Ruang (KKPR dan informasi ruang): 082120136353\n- UPT Peralatan dan Perbengkelan: 081324539882\n- UPT PSDA, O&P Perijinan: 089630476504\n\nKonsultasi langsung dapat dilakukan di kantor Dinas PUPR, dan untuk layanan tata ruang juga di Mal Pelayanan Publik (MPP).",
            ],
            [
                'Alur Pelayanan',
                'alur-pelayanan',
                "Halaman Layanan memuat tujuh alur pelayanan Dinas PUPR Kabupaten Indramayu. Setiap layanan disusun dengan susunan yang sama:\n\nPersyaratan pelayanan — dokumen yang harus disiapkan pemohon, dengan keterangan mana yang wajib dan mana yang hanya berlaku pada keadaan tertentu.\n\nTahapan — urutan perjalanan berkas beserta pihak yang menangani pada setiap tahap.\n\nWaktu penyelesaian — dihitung sejak berkas dinyatakan lengkap.\n\nBiaya — dinyatakan terbuka, termasuk bila layanan tersebut tidak dipungut biaya.\n\nPengaduan dan masukan — kanal yang dapat dihubungi untuk layanan yang bersangkutan.",
            ],
        ];

        foreach ($pages as [$title, $slug, $content]) {
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
    }
}
