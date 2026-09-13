# Dynamic CMS

CMS berbasis Laravel 12 untuk situs instansi/organisasi. Hampir seluruh isi
situs — menu, kategori, layanan, beranda, SEO, aksesibilitas, peran — dikelola
dari panel admin tanpa mengubah kode.

## Menjalankan

```bash
cd laradock
cp .env.example .env          # ubah port bila bentrok
docker compose up -d --build  # build pertama beberapa menit

docker compose exec workspace composer install
docker compose exec workspace cp -n ../.env.example ../.env
docker compose exec workspace php artisan key:generate
docker compose exec workspace php artisan migrate --seed
docker compose exec workspace php artisan storage:link
docker compose exec workspace npm install && npm run build
```

Situs: **http://localhost:8010** · Admin: **/admin/login**

Akun awal dari `AdminUserSeeder` (ubah lewat `ADMIN_*` di `.env`):

| Email | Kata sandi |
|---|---|
| `admin@example.test` | `password` |

> **Ganti kata sandi ini sebelum dipakai di luar mesin pengembangan.**
> Seeder hanya memasang kata sandi saat akun dibuat, jadi menjalankannya ulang
> tidak menimpa kata sandi yang sudah diganti.

Isi contoh (berita, layanan, FAQ, halaman) opsional:

```bash
docker compose exec workspace php artisan db:seed --class=DemoContentSeeder
```

Rincian stack ada di [`laradock/README.md`](laradock/README.md).

## Peta modul

| Area | URL admin | Catatan |
|---|---|---|
| Berita | `/admin/news` | Multi-kategori, tag, jadwal terbit |
| Kategori | `/admin/{modul}/category` | Satu tabel `categories`, dipisah kolom `type` |
| Halaman | `/admin/pages` | Tampil di `/halaman/{slug}` |
| Layanan | `/admin/services` | Persyaratan, tarif, tahapan per layanan |
| Dokumen | `/admin/documents` | Disk privat + route unduh terkontrol |
| FAQ | `/admin/faq` | Akordeon di situs publik |
| Media | `/admin/media` | Gambar/video publik, dokumen privat |
| Menu | `/admin/menus/{admin,public}` | Bertingkat tanpa batas, dari database |
| Pengaturan | `/admin/settings/*` | Umum, Beranda, Carousel, Sosial, SEO, Aksesibilitas, Footer |
| Pengguna & akses | `/admin/users`, `/admin/roles`, `/admin/permissions` | Matriks hak akses |
| Sistem | `/admin/audit-logs` | Jejak audit + pengosongan cache |

## Public API

Hanya-baca, berawalan `/api/public`, dibatasi laju. Semua respons memakai
amplop yang sama:

```json
{ "success": true, "message": "Data berhasil diambil", "data": [], "meta": {} }
```

`home`, `menus`, `settings`, `carousel`, `news`, `news/categories`,
`news/{slug}`, `services`, `services/categories`, `services/{slug}`,
`documents`, `documents/{slug}`, `faqs`, `pages/{slug}`.

API membaca lewat service yang sama dengan halaman Blade, sehingga keduanya
tidak pernah berbeda soal apa yang terbit.

## Aksesibilitas

Panel pengunjung menyediakan ukuran teks, mode penglihatan warna (protanopia,
deuteranopia, tritanopia, skala abu-abu), pembacaan halaman, penegasan tautan,
dan pengurangan animasi. Setiap kendali dapat dimatikan administrator di
`/admin/settings/accessibility`.

Yang dipegang sebagai prinsip, bukan sekadar fitur:

- Status tidak pernah hanya warna — selalu ikon + kata + warna.
- Carousel tidak berjalan sendiri (WCAG 2.2.2).
- Pengurutan bisa lewat papan ketik, bukan hanya seret.
- Preferensi diterapkan sebelum render pertama, jadi tidak ada kedipan.
- Fitur yang tidak didukung peramban dinyatakan terus terang, bukan tombol mati.

## Statistik pengunjung

Dashboard menampilkan pengunjung unik, kunjungan, tren 14 hari, halaman
terpopuler, dan sumber rujukan.

Yang **tidak** disimpan: alamat IP, user agent, dan query string rujukan.
Yang disimpan sebagai pengenal adalah `sha256(ip + user agent + tanggal + app key)`
— tidak dapat dibalik, dan **berganti setiap tengah malam**, sehingga bisa
menghitung pengunjung unik harian tetapi tidak bisa mengikuti seseorang
antar hari. Dari rujukan hanya nama host yang disimpan.

```dotenv
ANALYTICS_ENABLED=true          # false mematikan pencatatan sepenuhnya
ANALYTICS_RETENTION_DAYS=365    # ditegakkan oleh visitors:prune, terjadwal harian
```

Mematikannya membuat dashboard menyatakan hal itu terang-terangan, bukan
menampilkan nol yang bisa disalahartikan sebagai situs tanpa pengunjung.

Pencatatan gagal tidak pernah menggagalkan halaman: penulisan dibungkus
try/catch dan hanya dicatat ke log. Crawler (Googlebot, curl, permintaan tanpa
user agent) tidak ikut dihitung.

## Tampilan

| Bagian | Acuan | Catatan |
|---|---|---|
| Panel admin | shadcn-admin | Token OKLCH, mode terang/gelap, sidebar dapat diciutkan (Ctrl+B) + flyout sub-menu |
| Beranda | sarab + Airbnb | Palet resmi biru/jingga, kartu bergambar dengan badge, pencarian pil bersegmen |
| Hero slider | — | Lebar penuh 500–650 px, konten kiri 40–45%, penghitung `01 / 04` + bilah kemajuan |
| Navbar | Dribbble | Logo, kolom pencarian pil dengan filter menyatu, menu bertingkat, tombol CTA opsional |
| Pengumuman | — | Modal sekali per pengunjung, lalu menetap sebagai teks berjalan di atas navbar |
| Unggahan sosial | — | Galeri bujur sangkar di beranda yang menautkan ke unggahan aslinya |
| Halaman berita | AzNews | Aksen disesuaikan agar lolos WCAG AA |
| Halaman masuk | Horizon UI | Split-screen + CAPTCHA |
| Halaman galat | shadcn-admin | 401, 403, 404, 419, 429, 500, 503 |

Seluruh komponen admin membaca **token**, bukan warna literal, sehingga tema
dapat berganti tanpa menyentuh satu pun view.

### Hero slider

Slide dikelola di **`/admin/settings/carousel`** — bukan di dalam kode. Satu
slide terdiri atas kategori, judul, deskripsi, gambar, teks alternatif, tombol
dan tautan, ditambah jadwal tayang (`start_date` / `end_date`) dan sakelar
aktif. Urutannya diatur lewat daftar seret-dan-lepas di halaman yang sama;
tombol naik/turun tersedia agar tidak bergantung pada tetikus.

- **Duplikat** menyalin berkas gambarnya, bukan memakai jalur yang sama, supaya
  menghapus salinan tidak ikut menghapus gambar aslinya. Salinan selalu lahir
  dalam keadaan nonaktif.
- **Pratinjau** (`/admin/settings/carousel/preview`) merender komponen publik
  yang sama persis, tetapi ikut menampilkan slide yang nonaktif dan yang belum
  tiba jadwalnya — gunanya memang memeriksa sebelum ditayangkan.
- Perputaran otomatis 6 detik, berhenti saat kursor atau fokus berada di
  dalamnya, dan punya tombol jeda sungguhan (WCAG 2.2.2). Pada
  `prefers-reduced-motion` slide tidak berputar sendiri sama sekali.
- Gambar pertama dimuat dengan `fetchpriority="high"`, sisanya `loading="lazy"`.

Palet identitas: `#02468B` sebagai warna utama, `#EF8519` hanya sebagai bidang
isian — teks di atasnya memakai tinta gelap `#0b1b2b` (6,67:1), sebab putih di
atas jingga itu hanya 2,61:1 dan gagal AA. Untuk teks berwarna jingga dipakai
langkah yang lebih gelap, `#ad6012`.

### Warna dan huruf halaman publik

Diatur di **`/admin/settings/appearance`** — bukan di dalam kode:

| Setelan | Mengubah apa |
|---|---|
| Warna Utama | Header, tautan, tombol utama, penanda menu aktif, dan seluruh gradasi 50–950 yang diturunkan darinya |
| Warna Pendukung | Aksen: label kategori slider, tombol cari di navbar |
| Warna Footer | Latar footer, beserta warna judul, teks, tautan, dan garisnya |
| Jenis Huruf | Inter (bawaan), Sistem, atau Serif |

Yang membuat ini bukan sekadar penggantian warna: **setiap warna teks diturunkan,
bukan ditetapkan.** `App\Support\Color` mengubah warna pilihan ke OKLCH, membangun
gradasi yang mempertahankan rona, lalu menghitung rasio kontras WCAG untuk memilih
tinta di atasnya. Memilih kuning pucat menghasilkan tulisan gelap secara otomatis —
putih di atasnya hanya 1,7:1 dan gagal. Footer gelap mendapat teks terang, footer
terang mendapat teks gelap, tanpa ada yang perlu diingat oleh pengelola.

Secara teknis, variabel `--color-teal-*` milik Tailwind ditulis ulang saat runtime.
Halaman publik memang dibangun di atas utilitas itu, jadi mengganti nilainya
mengecat seluruh situs tanpa menyentuh satu pun `class` — dan tanpa palet kedua
yang bisa melenceng dari yang pertama. Pilihan huruf seluruhnya lokal atau bawaan
sistem; tidak ada berkas font dari pihak ketiga, sama alasannya dengan statistik
pengunjung yang tidak menyimpan IP.

### Pengumuman

Dikelola di **`/admin/settings`** → **Pengumuman** (`/admin/announcements`). Satu
catatan tampil dalam dua wujud:

- **Modal** — terbuka satu kali per pengunjung, memuat label, judul, keterangan,
  kode promo dengan tombol salin, dan tombol tindakan. Memakai `<dialog>` bawaan
  peramban, jadi jebakan fokus, tombol Escape, dan menonaktifkan halaman di
  belakangnya ditangani peramban, bukan kode kita.
- **Teks berjalan** — strip di atas navbar yang **tetap ada** setelah modal
  ditutup, dan dapat membuka modalnya kembali. Dua pengumuman aktif atau lebih
  membuatnya bergerak dari bawah ke atas.

Ingatan penutupan disimpan berdasarkan sidik isi pengumuman, sehingga mengubah
atau menambah pengumuman membuatnya muncul lagi bagi pengunjung lama — tanpa itu,
pengumuman baru tidak akan pernah terlihat oleh siapa pun yang sudah menutup yang
lama. Gerakan otomatis punya tombol henti sungguhan (WCAG 2.2.2), dan pada
`prefers-reduced-motion` strip tidak bergerak sama sekali — seluruh barisnya
ditampilkan sekaligus.

### Unggahan media sosial

Dikelola di **`/admin/social-posts`**: platform, gambar, keterangan, tautan ke
unggahan asli, tanggal, urutan, dan sakelar tampil. Muncul di beranda sebagai
seksi `social_posts` yang bisa dipindah, diberi judul, dan dibatasi jumlahnya
lewat **`/admin/settings/homepage`** seperti seksi lainnya.

**Cara menambah: tempelkan tautannya, lalu tekan Ambil Data.** Platform dikenali
dari alamatnya, dan gambar, nama akun, keterangan, tanggal, jumlah suka serta
komentar terisi sendiri. Setelah itu semuanya mengikuti sumbernya — bila suka
bertambah atau keterangannya diedit di platform, situs ikut berubah pada
sinkronisasi berikutnya (tiap jam, lewat `social:sync`).

Tautan album seperti `…/p/ABC123/?img_index=2` mengambil slide yang ditunjuk,
bukan selalu yang pertama.

**Yang bisa dan tidak bisa dibaca:**

| Platform | Bisa diambil | Syarat |
|---|---|---|
| Instagram | Gambar, nama akun, keterangan, tanggal, suka, komentar | `INSTAGRAM_USER_ID` + `INSTAGRAM_ACCESS_TOKEN`; **hanya unggahan akun Anda sendiri** |
| Facebook | Gambar, keterangan, tanggal, reaksi, komentar | `FACEBOOK_PAGE_ID` + `FACEBOOK_PAGE_TOKEN` |
| YouTube | Thumbnail, nama kanal, judul, tanggal, suka, komentar | `YOUTUBE_API_KEY` saja |
| X, TikTok | — | Tidak ada jalur baca gratis; isi manual |

Batas terpenting: **Graph API hanya membaca unggahan milik akun yang tokennya
Anda pegang.** Unggahan akun lain akan dilaporkan "tidak ditemukan pada akun
ini" — itu batas dari Meta, bukan dari sistem ini. Untuk kasus itu datanya diisi
manual, dan sakelar *Ikuti perubahan di platform* dimatikan agar tidak tertimpa.

Gambar **diunduh ke disk situs ini**, tidak di-hot-link. Hot-link akan membuat
peramban setiap pengunjung memanggil CDN platform — persis pelacakan yang
dihindari rancangan ini — dan URL-nya bertanda tangan yang kedaluwarsa, jadi
kartunya akan kosong dalam hitungan hari.

**Tidak ada penarikan di sisi pengunjung** — dan itu keputusan sengaja:

Skrip sematan resmi (`instagram.com/embed.js` dan sejenisnya) mengirim alamat IP,
user-agent, dan URL perujuk setiap pengunjung ke platform tersebut lalu menaruh
cookie — sebelum pengunjung melakukan apa pun. Itu bertentangan dengan keputusan
yang sudah diambil di tempat lain pada proyek ini: Inter di-host sendiri alih-alih
dari Google Fonts, dan statistik pengunjung tidak menyimpan IP sama sekali.

Karena itu pembacaan dilakukan **server, terjadwal** — platform mendengar dari
situs ini sekali sejam dan tidak pernah tahu apa-apa tentang orang yang membacanya.
Halaman publik tidak memuat satu pun berkas pihak ketiga; sebuah test memastikan
tidak ada `<iframe>` atau skrip luar yang menyelinap masuk.

Dua hal yang dijaga khusus: sinkronisasi yang gagal **tidak pernah** mengosongkan
angka atau gambar yang sudah ada (basi lebih baik daripada terhapus), dan angka
yang tidak dilaporkan platform **tidak pernah** ditulis sebagai 0 — ikonnya tampil
tanpa angka, dan itu lebih jujur.

### Unggahan Instagram

Tempelkan tautan unggahan di **`/admin/social-posts`** — sisanya terisi sendiri.
Ada dua cara menampilkannya, dipilih di **`/admin/settings/appearance`**:

| Pilihan | Tampilan | Biayanya |
| --- | --- | --- |
| Sematan resmi (bawaan) | Persis unggahan aslinya: carousel, video, suka & komentar selalu terbaru | Memuat `embed.js` dari instagram.com, jadi alamat IP setiap pengunjung beranda terkirim ke Meta |
| Kartu buatan situs ini | Meniru tata letak Instagram; gambar dilayani dari server sendiri | Angka mengikuti sinkronisasi terakhir, bukan detik ini |

Pengambilan datanya memakai jalur resmi saja, tidak pernah scraping:

- **Graph API** (`INSTAGRAM_USER_ID` + `INSTAGRAM_ACCESS_TOKEN`) untuk unggahan
  akun sendiri. Satu-satunya jalur yang melaporkan `like_count`,
  `comments_count`, dan seluruh slide carousel.
- **oEmbed resmi** (`INSTAGRAM_OEMBED_TOKEN`, berbentuk `{app-id}|{client-token}`)
  sebagai cadangan untuk unggahan akun lain. Hanya memberi nama akun dan satu
  gambar pratinjau — Meta menghapus angka suka dari oEmbed sejak Oktober 2020,
  jadi angka untuk unggahan akun lain diisi tangan atau dikosongkan.
- `INSTAGRAM_READ_COMMENTS=true` menambah pratinjau komentar teratas. Mati
  secara bawaan: perlu izin `instagram_manage_comments` dan satu permintaan
  tambahan per unggahan setiap sinkronisasi.

Gambar carousel disimpan satu baris per slide di `social_post_media`, diunduh ke
disk sendiri, dan slide yang dihapus di sumbernya ikut terhapus di sini. Video
disimpan sebagai bingkai pratinjaunya saja; berkasnya tetap di platform.

### Logo dan favicon

Ketiganya — **Logo**, **Favicon**, dan **Logo Panel Admin** — diunggah di
**`/admin/settings/general`**.

| Berkas | Dipakai di |
| --- | --- |
| Logo | Header publik; juga cadangan untuk dua slot lainnya |
| Favicon | `<link rel="icon">` publik dan admin; juga tanda kotak sidebar saat diciutkan |
| Logo Panel Admin | Sidebar panel admin (memanjang, tinggi ±40 piksel) |

Bila belum diunggah, setiap slot jatuh ke logo situs, dan bila itu pun kosong
header memakai ikon cadangan — jadi tidak ada tampilan yang rusak. Nama situs
tidak dicetak di sebelah logo, tetapi tetap dibawa sebagai nama tautan bagi
pembaca layar, dan judul tab admin memakai nama situs, bukan nama aplikasi.

Tanda kotak di sidebar **hanya muncul saat sidebar diciutkan**. Ditampilkan di
kedua keadaan, gambar yang sama akan tampil dua kali — satu memanjang, satu
terjepit ke dalam kotak 32×32 — jadi `.brand-square` disembunyikan selama
sidebar masih mengembang.

Tombol di sisi kanan navbar diisi dari **Tombol Header** dan **Tautan Tombol
Header** pada layar yang sama. Keduanya harus diisi; salah satu saja tidak
memunculkan apa pun, karena tombol tanpa tujuan adalah kendali mati.

### Tipografi

Satu jenis huruf, **Inter**, dengan peran dibawa oleh bobot: judul 700–800,
teks isi 400–500, tombol 600–700. Ukurannya memakai skala modular yang melar
mengikuti lebar layar (`--step--1` … `--step-5` di `resources/css/app.css`).

Font **di-host sendiri** di `public/fonts` (132 KB, dua subset). Bukan dari
Google Fonts: permintaan font mengirim alamat IP setiap pengunjung ke pihak
ketiga, dan itu bertentangan dengan cara statistik pengunjung sengaja dibuat
tidak menyimpan IP.

### Bank ikon

Ikon disimpan di tabel `icons` dan dipilih lewat dropdown bergrup, bukan
diketik manual. Sumbernya tetap `config/icons.php`:

```bash
node scripts/build-icons.mjs                 # perbarui config/icons.php
php artisan db:seed --class=IconSeeder       # dump ke tabel icons
```

Menjalankan ulang seeder aman: ikon dicocokkan berdasarkan nama, dan ikon yang
hilang dari set dinonaktifkan — bukan dihapus — agar menu atau kategori yang
masih memakainya tidak rusak.

## Perintah

```bash
php artisan test                                  # 367 test
php artisan migrate:fresh --seed                  # bangun ulang
php artisan db:seed --class=DemoContentSeeder     # isi contoh
node scripts/build-icons.mjs                      # perbarui config/icons.php
php artisan db:seed --class=CarouselSeeder        # empat slide hero contoh
php artisan db:seed --class=AnnouncementSeeder    # tiga pengumuman contoh
php artisan db:seed --class=SocialPostSeeder      # empat unggahan sosial contoh
php artisan visitors:prune                        # pangkas catatan kunjungan lama
php artisan social:sync                           # baca ulang angka dari platform (terjadwal tiap jam)
```

Beberapa perilaku hanya bisa dibuktikan di peramban sungguhan — tata letak,
waktu, dan penempatan tidak terlihat oleh pemeriksaan markup. Skrip berikut
menjalankan Chrome terhadap stack yang hidup:

```bash
node scripts/check-hero-slider.mjs      # perputaran, jeda, geser, papan tik
node scripts/check-carousel-admin.mjs   # DataTable, urutan, borang, pratinjau
node scripts/check-sidebar-flyout.mjs   # flyout sub-menu saat sidebar diciutkan
node scripts/check-public-theme.mjs     # warna & huruf dari admin benar-benar mengecat situs
node scripts/check-announcements.mjs    # modal, strip berjalan, jeda, fokus, navbar
```

## Catatan produksi

- **`APP_DEBUG=false`.** Wajib. Dengan debug menyala, respons DataTables ikut
  membawa SQL yang dieksekusi, dan halaman galat Laravel membocorkan jauh lebih
  banyak lagi.
- **CAPTCHA.** Tiga driver lewat `CAPTCHA_DRIVER`:
  `turnstile` (Cloudflare, untuk produksi — isi `TURNSTILE_SITE_KEY` dan
  `TURNSTILE_SECRET_KEY`), `math` (bawaan — pertanyaan aritmetika sederhana yang
  diverifikasi di session, tanpa akun pihak ketiga), dan `null` (dipakai test).
  Tantangan `math` sengaja **berupa teks, bukan gambar**: captcha gambar tidak
  terbaca oleh pembaca layar dan mengunci justru staf yang seharusnya dilayani.
  Provider lain ditambahkan dengan mengimplementasikan `CaptchaServiceInterface`.
- **Disk privat tidak dilayani HTTP** (`'serve' => false` pada disk `local`).
  Dokumen hanya keluar lewat route unduh, yang memeriksa visibilitas dan
  mencatat jumlah unduhan.
- **Cache.** Menu, pengaturan, dan data publik di-cache dan dibatalkan otomatis
  saat datanya berubah. `/admin/audit-logs` menyediakan pengosongan manual untuk
  perubahan yang terjadi di luar CMS.
