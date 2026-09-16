<?php

/**
 * Peta Pengaduan.
 */
return [

    /*
     | Batas wilayah yang dilayani, pada disk publik.
     |
     | Ada bila sudah diambil dengan `php artisan peta:batas`; bila tidak ada,
     | peta bekerja seperti biasa tanpa batas — sebuah peta yang menolak tampil
     | karena berkas batasnya belum diunduh tidak menolong siapa pun.
     */
    'boundary_path' => 'peta/batas-wilayah.geojson',

    /*
     | Seberapa jauh peta boleh digeser ke luar batas, dalam derajat.
     |
     | Bukan nol: sebuah titik pengaduan yang persis di tepi wilayah akan
     | terhimpit ke pinggir layar tanpa ruang sedikit pun di sekelilingnya,
     | dan orang tidak dapat melihat jalan mana yang dimaksud.
     */
    'boundary_padding' => 0.05,

    /*
     | Berapa lama tautan foto bukti berlaku bagi instansi tujuan penerusan.
     |
     | Cukup lama untuk ditindaklanjuti, cukup pendek untuk tidak tertinggal
     | terbuka di grup WhatsApp bertahun kemudian.
     */
    'evidence_link_days' => 14,

];
