# Chatbot multi-kanal — fondasi

Status: **berfungsi menyeluruh dan teruji** — skema, mesin percakapan, API
internal, command petugas, layar admin, layanan Python, dan editor alur visual.
213 test PHP (bagian dari 562 test proyek), 16 test Python, 73 pemeriksaan
peramban. Bot Telegram sudah tersambung dan menunggu pesan.

## Keputusan yang sudah diambil

## Bantuan AI

`/admin/bot/ai`. Pilih layanan (Groq, OpenAI, OpenRouter, atau Ollama di server
sendiri), pilih model dari daftar, **isi kunci API**. Itu saja — alamat API
tidak pernah diketik: itu string persis yang tidak punya cara diperiksa oleh
manusia, dan satu karakter salah menghasilkan bot yang diam-diam berhenti
membantu. Untuk Ollama, kolom kunci bahkan hilang — tidak ada siapa pun untuk
diautentikasi.

Groq, OpenAI, OpenRouter, dan Ollama sama-sama berbicara `/chat/completions`,
jadi satu penggerak melayani semuanya. Satu kelas per vendor hanya akan jadi
empat salinan dua puluh baris yang sama, masing-masing bisa melenceng.

AI terhubung lewat **node `Tanya Jawab AI`** di editor alur — bukan menempel di
belakang layar. Ia bisa diletakkan, disambungkan, dan diatur seperti node lain:
sumber mana yang boleh dipakai menjawab, watak jawabannya, batas giliran, dan
kalimat penutupnya.

**Pilihan 4 pada menu utama kini percakapan sungguhan.** Node itu **menahan
gilirannya**: setelah menjawab ia menunggu pertanyaan berikutnya, dan giliran
sebelumnya ikut dikirim — sehingga "berapa biayanya?" tepat setelah bertanya
soal izin dipahami sebagai lanjutan, bukan orang asing baru. Tanpa itu, setiap
pesan adalah orang asing dan percakapannya terasa seperti kotak pencarian
dengan langkah tambahan.

Tiga jalan keluar, semuanya disengaja: pengguna berkata **selesai**, model
**tidak bisa menjawab** dari isi situs, atau **batas giliran** tercapai. Bila
model tidak bisa menjawab, pertanyaannya **tidak ditanyakan ulang** — sudah
diingat, jadi langsung diteruskan ke pencarian kata kunci lalu ke petugas.
Meminta orang mengetik ulang kalimat yang sama persis adalah yang membuat bot
terasa seperti formulir.

Isi yang diberikan ke model **dikelompokkan per jenis dan diberi tanggal**, dan
model diberi tahu hari ini tanggal berapa. Tanpa itu semuanya tiba sebagai satu
daftar tak berbeda: ditanya "ada berita apa saja hari ini", model tidak punya
cara tahu baris mana yang berita dan menjawab dari apa pun yang terdekat — dan
"hari ini" tidak punya patokan sama sekali. Setiap sumber juga mendapat jatah
yang seimbang; sebelumnya satu FAQ yang panjang bisa mendorong seluruh berita
keluar dari prompt.

Konteksnya dibaca **utuh**, bukan disaring kata kunci lebih dulu: pertanyaan
yang susunannya berbeda dari entri FAQ yang menjawabnya akan gagal ditemukan
pencarian kata kunci dan diteruskan ke manusia padahal jawabannya ada satu baris
di sebelah. Membaca itulah gunanya model.

**Dua kemampuan lain, keduanya bersifat membantu:**

- **Membaca kalimat bebas pada menu.** Warga yang menulis "jalan depan rumah saya rusak parah" diarahkan ke Pengaduan, bukan ditolak karena tidak membalas angka. Tebakan hanya diterima bila cocok dengan pilihan yang **benar-benar ada** — model yang menjawab "9" pada menu tiga pilihan tidak mengubah apa pun, dan model yang ragu wajib menjawab TIDAK. Mengarahkan orang ke cabang yang salah karena menebak lebih buruk daripada menampilkan menunya lagi.
- **Menyusun jawaban dari FAQ.** Jawaban ditulis **hanya** dari isi yang ditemukan, dan model diperintahkan menjawab `TIDAK TAHU` bila sumbernya tidak menjawab — yang lalu meneruskan pertanyaan itu ke petugas. Jawaban salah yang terdengar yakin dari layanan pemerintah jauh lebih berbahaya daripada "belum ada jawabannya".

**AI tidak pernah menjadi penopang.** Model gagal, lambat, kehabisan kuota, atau
tidak dikonfigurasi — alurnya bekerja persis seperti sebelumnya. Batas waktunya
delapan detik dengan satu percobaan ulang; orang yang menunggu di chat merasakan
tiga detik, dan jalur mundurnya menjawab seketika. Ada test untuk setiap jalur
mundur itu.

**Yang dikirim keluar hanya teks pesan.** Foto, titik lokasi, dan nomor telepon
tidak pernah masuk prompt — ada test yang membuktikannya, dan layar yang
menyalakannya mengatakannya terus terang. Ini satu-satunya fitur di proyek ini
yang mengirim tulisan warga ke pihak ketiga; pilih Ollama bila itu tidak boleh
terjadi. Kunci API disimpan terenkripsi, tidak pernah ditampilkan ulang, dan
tidak pernah masuk audit log.

## Waktu dan bahasa

`APP_TIMEZONE=Asia/Jakarta` dan `APP_LOCALE=id`. Sebelumnya aplikasi berjalan
pada UTC, sehingga pengaduan yang masuk pukul 14.00 WIB tampil sebagai 07.00 —
operator mengira laporan itu datang pagi tadi. Tanggal pun tertulis
"Sunday, 13 September" di panel yang seluruhnya berbahasa Indonesia.

Baris yang ditulis **sebelum** perubahan ini tersimpan dalam UTC dan akan
terbaca tujuh jam lebih awal. Itu data dari masa sebelum sistem dipakai;
bila ada catatan lama yang penting, waktunya perlu digeser sekali.

## Pengaturan kanal

`/admin/bot/channels`. Kredensial WhatsApp dan Telegram diisi dari panel —
tidak perlu menyentuh berkas di server. Tombol **Uji Koneksi** menanyakan
langsung ke platform siapa bot ini; token yang sekadar tersimpan tidak
membuktikan apa pun.

Sebuah layar setelan adalah tempat paling mudah membocorkan rahasia, jadi ada
tiga lapis:

1. **Terenkripsi saat disimpan.** Siapa pun yang memegang token WhatsApp bisa mengirim pesan atas nama instansi; nilai polos akan ikut ke setiap cadangan basis data dan setiap `SELECT *`.
2. **Tidak pernah ditampilkan ulang.** Kolomnya hanya menunjukkan `••••••••5b4a`, dan kolom kosong berarti "biarkan" — sehingga rahasia tidak bisa dibaca dari sumber halaman oleh siapa pun yang dapat membuka layar itu.
3. **Tidak pernah masuk audit log.** Yang dicatat adalah *nama* medan yang berubah, bukan nilainya. Inilah alasan semula saya menaruhnya di `.env`; masalahnya diselesaikan, bukan dihindari.

`.env` tetap berlaku sebagai cadangan: deployment yang sudah mengaturnya di
sana terus bekerja, dan panel menang begitu kolomnya diisi.

**Layanan Python mengambil kredensial ini dari Laravel saat dijalankan**
(`GET /api/bot/config`, di balik gerbang token yang sama). Tanpa itu, kunci yang
diisi di panel hanya akan mengendap di basis data sementara bot tetap memakai
environment-nya sendiri — layarnya tampak bekerja dan tidak mengubah apa pun.
Kanal yang dinonaktifkan tidak diserahkan sama sekali.

| Hal | Pilihan | Alasan |
|---|---|---|
| WhatsApp | **Meta Cloud API resmi** | Nomor tidak berisiko diblokir permanen seperti pada klien tidak resmi, dan pengiriman tidak bergantung pada satu ponsel yang harus tetap menyala |
| Telegram | Bot API resmi | Satu-satunya jalur yang masuk akal |
| Editor alur | Vanilla JS + SVG | Konsisten dengan proyek ini yang sudah menulis klien DataTables sendiri tanpa jQuery; tanpa React di aplikasi Blade |
| Kredensial | `.env`, bukan tabel `settings` | `AuditLogger` mencatat nilai lama dan baru setiap perubahan setelan — token yang diketik di formulir akan tersimpan terbaca di tabel audit |

## Tabel

**Kanal dan orang:** `bot_channels`, `bot_contacts`
**Alur:** `bot_flows`, `bot_nodes`, `bot_edges`, `bot_flow_versions`
**Percakapan:** `bot_conversations` (sesi, satu baris panas per orang), `bot_messages` (transkrip, append-only)
**Pengaduan:** `complaint_categories`, `complaints`, `complaint_attachments`, `complaint_updates`
**Perutean:** `bot_recipients`, `bot_recipient_categories`, `bot_data_sources`
**Jembatan:** `bot_outbox`, `bot_webhook_events`

Beberapa keputusan yang tidak terbaca dari nama tabel:

- **Node dan edge adalah baris, bukan satu blob JSON.** Satu node bisa divalidasi dan diubah tanpa menulis ulang seluruh diagram, dan sesi editor yang setengah tersimpan tidak merusak node lain.
- **`bot_flow_versions` menyimpan snapshot tiap terbit.** Percakapan yang sedang berjalan tetap terpaku pada versi saat ia dimulai, jadi mengubah alur tidak memindahkan orang ke pertanyaan lain di tengah pengisian.
- **Sesi dan transkrip terpisah.** Sesi adalah satu baris yang diperbarui setiap giliran; transkrip tumbuh tanpa batas dan dipangkas terjadwal tanpa menyentuh sesi hidup.
- **`bot_webhook_events` mencatat id pesan sebelum diproses.** Meta dan Telegram mengulang webhook yang mereka anggap gagal; tanpa ini satu pengaduan bisa tercatat dua kali. Keunikannya ditegakkan database, bukan pengecekan yang rawan balapan.
- **`bot_outbox` membuat pengiriman tahan gagal.** Notifikasi yang hilang karena Meta sesaat tak terjangkau adalah pengaduan yang tak seorang pun diberi tahu.
- **Tiket dibuat acak, bukan berurutan.** Kode ini dikutip kembali lewat WhatsApp untuk membaca sebuah pengaduan; kode berurutan akan membuat semua pengaduan lain terbaca hanya dengan menghitung. Abjadnya juga membuang `O`, `0`, `I`, `1` yang sering salah disalin.
- **Syarat bukti ada di kategori, bukan di kode.** `requires_photo` dan `requires_location` dibaca alur, jadi kategori baru langsung berperilaku benar tanpa deployment.

## Struktur satu node

Inilah kontrak yang dibaca editor visual, mesin alur, dan runtime Python — satu bentuk, satu sumber (`BotFlow::toGraph()`):

```json
{
  "flow":  { "slug": "alur-utama", "name": "Alur Utama", "version": 1 },
  "nodes": [
    {
      "key": "menu_utama",
      "type": "menu",
      "label": "Menu Utama",
      "position": { "x": 260, "y": 260 },
      "config": {
        "text": "Silakan pilih dengan membalas angkanya:",
        "options": [
          { "value": "1", "label": "Pengaduan" },
          { "value": "2", "label": "Cek Aduan" },
          { "value": "3", "label": "Informasi Layanan" }
        ],
        "invalid_message": "Pilihan tidak dikenali.",
        "on_invalid": "repeat",
        "max_retries": 3
      }
    },
    {
      "key": "bukti_aduan",
      "type": "input",
      "config": {
        "text": "Kirimkan foto lokasinya, lalu bagikan titik lokasi.",
        "input": "image_location",
        "requirement_from": "category",
        "store_as": "evidence",
        "on_invalid": "repeat"
      }
    }
  ],
  "edges": [
    { "from": "menu_utama", "to": "menu_kategori", "condition": "1", "label": "Pengaduan" },
    { "from": "bukti_aduan", "to": "simpan_aduan", "condition": "valid", "label": null }
  ]
}
```

**Tipe node** (`App\Support\BotNodes`): `start`, `message`, `menu`, `input`, `data_source`, `action`, `end`.
**Kondisi edge:** nilai pilihan menu, atau `valid` / `invalid` / `exhausted` / `back` / `category`.

Dua hal yang membuat alur contoh tidak perlu di-hard-code:

- `"options_from": "complaint_categories"` — menu kategori diisi dari tabel, jadi menambah kategori menambah pilihan menu tanpa menyentuh alur.
- `"requirement_from": "category"` — wajib-tidaknya foto dan lokasi ditentukan kategori yang barusan dipilih, bukan oleh node yang tahu bahwa "jalan" butuh gambar.

## Menjalankan

```bash
php artisan migrate
php artisan db:seed --class=ComplaintCategorySeeder   # Jalan, Irigasi, Lainnya
php artisan db:seed --class=BotFlowSeeder             # alur utama: 13 node, 21 sambungan
```

Kedua kanal lahir **nonaktif**. Kanal yang menjawab publik begitu di-seed adalah kanal yang belum sempat ditinjau siapa pun.

## Mesin percakapan

`App\Services\Bot\BotEngine` menjalankan graf tersebut. Satu giliran:
cari posisi orang itu → biarkan node membaca jawabannya → telusuri edge,
menjalankan setiap node yang tidak perlu jawaban, sampai ada yang bertanya
atau alur berakhir. Semua yang terucap dua arah ditulis ke transkrip.

**Mesin tidak mengirim apa pun.** Ia mengembalikan daftar `OutgoingMessage`,
dan transport yang memostingnya. Itulah yang membuat seluruh percakapan dapat
diuji tanpa jaringan, dan yang memungkinkan pengiriman gagal diulang tanpa
menjalankan ulang alurnya.

Tiap handler node punya dua bagian: `enter()` saat percakapan tiba di node itu,
dan `receive()` saat jawaban datang. Pemisahan ini yang membuat mesin bisa
berjalan melewati beberapa node dalam satu giliran lalu berhenti di yang pertama
benar-benar bertanya.

Hal-hal yang ditangani dan diuji:

- **Foto dan lokasi datang sebagai dua pesan terpisah** — itu cara orang benar-benar mengirimnya. Node menyimpan bagian yang sudah tiba, jadi yang mengirim foto dulu tidak dimintai foto lagi.
- **Syarat bukti dibaca dari baris kategori.** Mengubah "Lainnya" jadi wajib foto langsung mengubah percakapan tanpa menyentuh alur — ada testnya.
- **Menu menerima angka maupun kata.** Orang mengetik "pengaduan" sesering mengetik "1".
- **Daftar yang dikirim diingat**, jadi membalas "2" membuka item kedua yang benar-benar ditawarkan — bukan item kedua menurut keadaan situs semenit kemudian.
- **"menu" keluar dari formulir setengah jadi** dari mana pun. Tanpa ini, orang yang tersangkut hanya bisa menunggu sesi kedaluwarsa.
- **Percakapan terpaku pada versi alur saat ia dimulai.**
- **Batas percobaan** per node, lalu mengikuti aturan `on_invalid` miliknya sendiri.
- **Penjaga siklus**: alur yang berputar tanpa pernah bertanya dihentikan dan dilaporkan, bukan menggantung diam.
- **Tiket tidak ditemukan dijawab sama** baik untuk tiket yang tak pernah ada maupun milik orang lain — membedakannya akan memetakan tiket mana yang nyata.
- **Notifikasi hanya ke petugas yang mencentang kategori itu.** Tidak ada yang mencentang berarti tidak ada yang dikirimi; jatuh-balik ke "kirim ke semua" akan diam-diam membatalkan penugasan yang diatur admin.
- **Notifikasi masuk `bot_outbox`, tidak dikirim langsung.** Platform yang sesaat tak terjangkau tidak boleh membuat pelapor kehilangan laporannya.

### Ketika orang membalas di luar yang ditentukan

Ini kejadian sehari-hari, bukan kasus tepi — jadi ditangani berlapis:

1. **Jenis balasannya disebutkan.** Kirim pesan suara saat diminta uraian, jawabannya: *"Anda mengirim pesan suara, padahal yang ditunggu adalah penjelasan sepanjang minimal 10 karakter."* Bukan "jawaban tidak sesuai" yang tidak memberi tahu apa pun.
2. **Yang benar dijelaskan, diturunkan dari setelan node itu sendiri** (`App\Services\Bot\ExpectationDescriber`) — jadi alur yang digambar admin menjelaskan dirinya sendiri tanpa mereka menulis pesan galat untuk setiap cabang. Baris itu tidak ditambahkan bila wording admin sudah menyebutkannya.
3. **Pertanyaannya diulang sejak kegagalan kedua.** Di layar ponsel pertanyaan aslinya sudah tergulung hilang di balik percobaan-percobaan tadi; memberi tahu orang bahwa ia salah tanpa menunjukkan pertanyaannya adalah cara percakapan mandek.
4. **Setelah batas percobaan**, edge `exhausted` yang digambar pada graf diikuti — dan itu **mengalahkan** setelan `on_invalid`. Editor adalah tempat admin menyatakan apa yang harus terjadi; garis yang mereka tarik tidak boleh dibatalkan oleh default yang tak pernah mereka lihat.
5. **Jenis pesan apa pun diterima API** — stiker, kontak, video. Menjawab 422 akan membuat platform mengulanginya selamanya alih-alih membiarkan alur berkata "itu bukan yang saya minta".
6. **Balasan kosong bukan jawaban**, dan yang setengah benar tetap disimpan: pengirim foto tanpa lokasi hanya dimintai lokasinya.

Templat wording memakai `{placeholder}`, **bukan Blade** — sengaja. Templat ini
ditulis di formulir admin oleh orang yang bukan pengembang, dan Blade akan
mengeksekusi PHP dari formulir itu. Placeholder tak dikenal dibiarkan tampak apa
adanya agar salah ketik terlihat di chat, bukan menghentikan percakapan.

## API internal Laravel ↔ Python

Pembagian kerjanya: **Python memiliki platform, Laravel memiliki aturan.**
Layanan Python menerima webhook, memverifikasi tanda tangan terhadap *raw body*
(hanya ia yang memilikinya), mengunduh media, lalu mengirim satu bentuk
ternormalisasi ke sini. Semua sesudahnya — sesi, alur, pengaduan, kewenangan —
diputuskan di Laravel, jadi aturannya ada di satu tempat, bukan dua yang bisa
saling melenceng.

| Endpoint | Untuk apa |
|---|---|
| `POST /api/bot/inbound` | Satu pesan masuk; mengembalikan balasan yang harus dikirim |
| `POST /api/bot/outbox/pull` | Mengambil notifikasi yang menunggu dikirim |
| `POST /api/bot/outbox/report` | Melaporkan hasil pengiriman (`sent` / `failed`) |

Seluruhnya di balik `X-Bot-Token` (`config/bot.php`, dari `.env`), dibandingkan
dengan `hash_equals` — `===` pada rahasia membocorkan panjang dan awalannya lewat
waktu eksekusi. **Tanpa token terpasang, endpoint tertutup, bukan terbuka:**
deployment yang lupa mengaturnya harus gagal-tertutup.

Contoh satu pesan masuk:

```json
POST /api/bot/inbound          X-Bot-Token: <rahasia>
{
  "channel": "whatsapp",
  "external_id": "wamid.HBgM...",
  "from": "628120000001",
  "type": "location",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "sender_name": "Warga Sukamaju"
}
→ { "status": "handled",
    "messages": [ { "type": "text", "body": "Pengaduan Anda tercatat...", "media_path": null } ] }
```

Dua hal yang ditangani di sini dan diuji:

- **Pengiriman ulang ditangani sekali.** Meta dan Telegram mengulang webhook yang mereka anggap gagal. Keunikan `external_id` ditegakkan **database**, bukan baca-lalu-tulis: dua ulangan yang tiba bersamaan sama-sama lolos pengecekan baca. Yang kedua dijawab `duplicate` tanpa pesan.
- **Outbox diambil, bukan didorong.** Bot yang sempat mati cukup memungut yang terlewat saat hidup lagi. Pengambilan mengklaim barisnya dalam pernyataan yang sama, jadi dua pekerja tidak mengirim notifikasi yang sama. Gagal kirim kembali ke antrean dengan jeda yang melebar, lalu menyerah setelah 5 percobaan **dengan alasannya tercatat** — menyerah diam-diam menghilangkan notifikasi, mengulang selamanya menghantam platform yang menolak karena suatu sebab.

## Command petugas

`/proses`, `/selesai`, dan `/tolak` diikuti nomor tiket, dikirim dari nomor
WhatsApp atau grup Telegram petugas. Foto yang menyertai `/selesai` disimpan
sebagai **bukti tindak lanjut**, bukan salinan kedua laporan aslinya. Teks
setelah tiket menjadi catatan perkembangan.

Kewenangan datang dari baris `bot_recipients` tempat pesan itu tiba, bukan dari
isi pesannya:

- Hanya tujuan terdaftar yang dicentang `can_command` yang boleh mengubah status. Grup yang sekadar diberi tahu tidak bisa menutup pengaduan.
- Petugas hanya boleh menyentuh kategori yang ditugaskan kepadanya — diberi tahu soal jalan berlubang tidak boleh membuat seseorang menutup aduan irigasi yang tak pernah ditunjukkan padanya.
- Warga biasa yang mengetik `/selesai` diperlakukan sebagai percakapan biasa; ia bukan petugas.

## Layar admin

| Layar | Untuk siapa |
|---|---|
| `/admin/complaints` | Petugas yang menggarap antrean: kartu jumlah per status, DataTable dengan saringan status/kategori/kanal |
| `/admin/complaints/{id}` | Rincian: uraian, titik lokasi, foto pelapor, foto tindak lanjut, riwayat perubahan, dan formulir ubah status |
| `/admin/bot/conversations` | Riwayat percakapan, disaring per kanal, status, dan rentang tanggal |
| `/admin/bot/conversations/{id}` | Transkrip bergaya aplikasi pesan, plus keadaan sesi dan jawaban yang terkumpul |

Beberapa keputusan yang tidak terbaca dari tangkapan layar:

- **Lampiran dilayani lewat rute terkendali, bukan URL disk publik.** Ini foto jalan, rumah, atau pekarangan seseorang yang dikirim secara privat. Id di URL bukan akses: izinnya diperiksa, sama seperti modul dokumen.
- **Bukti tindak lanjut disimpan terpisah dari foto pelapor** (`kind`), jadi keduanya tidak tertukar pada rincian maupun pada laporan.
- **Transkrip bergaya aplikasi pesan**: kepala percakapan dengan foto profil (atau inisial), nama, @username, nomor telepon yang bisa ditekan untuk menelepon, kanal asal, dan kapan terakhir terlihat. Gelembung kiri-kanan dengan avatar pada pesan masuk, penanda hari, dan penanda *Operator* pada balasan yang ditulis dari panel.
- **Foto profil hanya ada pada Telegram** (`getUserProfilePhotos`); WhatsApp Cloud API tidak menyediakannya sama sekali, jadi kontak WhatsApp memakai inisial — itu keadaan normal, bukan kegagalan. Diambil sekali per orang, tidak tiap pesan, dan disajikan lewat rute bergerbang izin karena tak seorang pun menerbitkan foto itu.
- **Nomor telepon ditutup sebagian di daftar, utuh pada satu percakapan yang dibuka.** Daftar terbuka sepanjang hari di atas meja; membuka satu percakapan adalah tindakan sengaja, dan operator yang harus menelepon balik tidak bisa memutar titik-titik.
- **Pengaduan dapat dibalas dari panel** (`Balas Pelapor` pada layar rinciannya): pesan, boleh dengan foto, terkirim ke kanal tempat pengaduan itu masuk. Perubahan status juga punya pilihan *Beri tahu pelapor*. Sebelumnya catatan operator hanya masuk riwayat internal dan pelapor tidak pernah mendengarnya kecuali ada petugas yang kebetulan memakai command dari ponselnya.
- Balasan itu **masuk antrean outbox**, bukan dikirim langsung — permintaan yang menulis balasan tidak boleh gagal karena platform sesaat tak terjangkau, dan balasan yang tidak sampai lebih buruk daripada yang telat semenit. Ia juga **ditulis ke transkrip**, supaya percakapannya terbaca sebagai satu pertukaran, bukan separuh milik pelapor saja.
- Pengaduan yang tidak berasal dari chat **tidak menampilkan formulir balasan sama sekali**, dengan alasannya tertulis — lebih baik daripada formulir yang gagal saat dikirim.
- **Transkrip tetap tidak punya kotak balasan.** Membalas sebuah pengaduan yang sudah tercatat itu di luar jalur dan memang ditunggu pelapor; mengetik di transkrip yang sedang hidup akan memotong pertanyaan yang sedang diajukan alur. Dua hal berbeda, dan ada test untuk masing-masing.
- **Peta kecil dirakit di server ini**, bukan disematkan. `App\Services\Maps\MapSnapshot` mengambil sembilan petak OpenStreetMap, menjahitnya, memotong di titiknya, menggambar penandanya, lalu menyimpan hasilnya. Peramban operator **tidak pernah menghubungi penyedia peta** — sematan atau gambar yang di-hot-link akan menyerahkan koordinat pengaduan seseorang dan alamat operator kepada penyedia itu, pada setiap kali halaman dibuka. Satu kali ambil ±1 detik, sesudahnya instan; dua pengaduan di tikungan yang sama berbagi satu gambar. Gagal mengambil petak berarti tidak ada gambar, bukan halaman rusak — koordinatnya tetap tercetak.
- **Transkrip setinggi layar dan menggulir di dalamnya**, dibuka pada pesan terbaru. Dibiarkan memanjang, satu percakapan panjang mendorong keterangan sesi dan segalanya ke bawah layar.
- **Lampiran foto dibuka sebagai popup**, bukan tab baru dan bukan unduhan. Membuka di tab baru menghilangkan pengaduan yang sedang dibaca dan menyisakan gambar telanjang tanpa jalan kembali. Popup-nya `<dialog>` bawaan peramban, Escape menutup, fokus kembali ke thumbnail yang diklik.
- **Status memakai warna + ikon + kata.** Warna saja tidak mengatakan apa pun kepada pembaca yang tidak membedakan rona itu (WCAG 1.4.1).
- **Modul izin `complaint` tidak punya `create`** — pengaduan tidak pernah dibuat dari panel, ia hanya tiba lewat kanal. Kemampuan yang tidak diperiksa apa pun adalah kebohongan di layar hak akses.

## Yang belum dikerjakan

1. Editor alur visual (SVG, drag-and-drop, panel properti per node)
3. Layanan Python: webhook Meta Cloud API + Telegram, verifikasi tanda tangan
4. Layar admin: riwayat percakapan, pengaduan (peta + lampiran), penerima notifikasi
5. Command `/proses` dan `/selesai` dari WhatsApp/grup Telegram
6. Dasbor statistik
