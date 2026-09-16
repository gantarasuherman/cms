<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ke mana sebuah pengaduan diteruskan ketika bukan kewenangan dinas ini.
 *
 * Barisnya data, bukan daftar di dalam kode: instansi tujuan bertambah dan
 * berkurang, nomornya berganti, dan orang yang tahu hal itu adalah operator —
 * bukan orang yang dapat menyunting berkas di server.
 *
 * Dua templat pesan, bukan satu. Yang dibaca instansi tujuan dan yang dibaca
 * warga adalah dua kalimat berbeda: satu berisi rincian laporan dan permintaan
 * tindak lanjut, satunya menerangkan mengapa laporannya berpindah tangan.
 * Menyatukannya berarti salah satu dari keduanya selalu terasa keliru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Nomor WhatsApp format internasional tanpa tanda baca.
            $table->string('phone', 32);
            $table->string('contact_person')->nullable();
            $table->text('description')->nullable();
            $table->text('target_template')->nullable();
            $table->text('reporter_template')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complaint_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            // Tujuannya boleh dihapus kelak; disposisinya tetap tercatat,
            // karena ia bagian dari riwayat sebuah laporan.
            $table->foreignId('disposition_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_name');
            $table->string('target_phone', 32);
            $table->text('note')->nullable();
            $table->string('source', 16)->default('admin');
            $table->string('source_actor')->nullable();
            $table->timestamps();
        });

        /*
         | Di mana seorang petugas berada dalam triase yang sedang berjalan.
         |
         | Petugas tidak memakai alur percakapan warga — mereka memakai
         | perintah — jadi triase bermenu ini perlu ingatannya sendiri. Satu
         | baris per nomor petugas: satu laporan diselesaikan dulu sebelum
         | yang berikutnya, sehingga tidak ada pertanyaan "angka 2 ini untuk
         | laporan yang mana".
         */
        Schema::create('officer_triages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('step', 24);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('bot_recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_triages');
        Schema::dropIfExists('complaint_dispositions');
        Schema::dropIfExists('disposition_targets');
    }
};
