<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lewat mana sebuah penerusan sampai ke instansi tujuan.
 *
 * Bawaannya `link`, yang tidak memungut biaya sama sekali: bot menyiapkan
 * kalimatnya lengkap sebagai tautan wa.me, dan petugas menekannya untuk
 * mengirim dari WhatsApp miliknya sendiri.
 *
 * Alasannya bukan penghematan semata. Cloud API menolak teks bebas kepada
 * nomor yang belum pernah menghubungi bot dalam 24 jam terakhir — dan instansi
 * tujuan, menurut sifatnya, tidak pernah menghubungi bot pengaduan. Mengirim
 * lewat API berarti wajib memakai template berbayar yang harus disetujui Meta
 * lebih dahulu; sampai template itu ada, penerusan lewat API tidak akan sampai
 * sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disposition_targets', function (Blueprint $table) {
            $table->string('channel', 16)->default('link')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('disposition_targets', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
