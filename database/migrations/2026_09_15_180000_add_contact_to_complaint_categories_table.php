<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp yang diberikan kepada warga untuk tiap bidang.
 *
 * Sengaja TIDAK memakai nomor petugas PIC yang sudah ada di `bot_recipients`.
 * Keduanya tampak sama — sebuah nomor per kategori — tetapi menjawab dua
 * pertanyaan yang berbeda:
 *
 *   bot_recipients        : siapa yang DIBERI TAHU ketika ada laporan masuk
 *   contact_phone di sini : nomor apa yang BOLEH DIBERIKAN kepada warga
 *
 * Menyatukan keduanya berarti nomor pribadi seorang petugas piket tersiar ke
 * setiap orang yang bertanya, diam-diam, hanya karena ia dicentang sebagai
 * penerima notifikasi. Itu keputusan yang harus diambil sadar, bukan
 * diwariskan dari kolom lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            // Nama yang disebut ke warga: "Bidang Bina Marga", bukan nama orang.
            // Satu bidang berganti petugas jauh lebih sering daripada berganti
            // nama, dan warga menyimpan pesan ini di ponselnya bertahun-tahun.
            $table->string('contact_name')->nullable()->after('description');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone']);
        });
    }
};
