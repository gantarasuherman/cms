<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template WhatsApp untuk penerusan ke nomor yang belum pernah menyapa bot.
 *
 * Cloud API hanya mengizinkan teks bebas di dalam 24 jam sejak orang itu
 * mengirim pesan. Instansi tujuan, menurut sifatnya, tidak pernah mengirim
 * pesan ke bot pengaduan — jadi satu-satunya cara bot menghubunginya sendiri
 * adalah template yang sudah disetujui Meta lebih dahulu. Template berbayar;
 * itulah harga dari pengiriman yang berjalan tanpa ditunggui.
 *
 * Kosong berarti tujuan ini tidak memakai template — dan kanal `whatsapp`
 * tanpa template hanya akan berhasil bila jendela 24 jam kebetulan terbuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disposition_targets', function (Blueprint $table) {
            $table->string('template_name')->nullable()->after('channel');
            $table->string('template_language', 16)->default('id')->after('template_name');
        });

        Schema::table('bot_outbox', function (Blueprint $table) {
            // Nama template dan parameternya, bila barisnya dikirim sebagai
            // template alih-alih teks bebas.
            $table->json('template')->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('disposition_targets', function (Blueprint $table) {
            $table->dropColumn(['template_name', 'template_language']);
        });

        Schema::table('bot_outbox', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
