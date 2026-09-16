<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warna pin peta untuk tiap jenis pengaduan.
 *
 * Nullable dengan sengaja: kosong berarti "pakai bawaan", dan bawaannya
 * diambil dari urutan palet tetap di dalam kode. Menyimpan warna hasil hitungan
 * ke setiap baris akan membekukan pilihan yang tidak pernah benar-benar dibuat
 * siapa pun, dan mengubah paletnya kelak tidak akan mencapai baris lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
