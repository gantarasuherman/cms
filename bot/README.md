# Layanan bot

Menjembatani WhatsApp dan Telegram dengan Laravel. Layanan ini **tidak
mengambil keputusan apa pun**: ia menerima pesan, menormalkannya, mengirimkannya
ke API internal Laravel, lalu mengirim balasan yang dikembalikan. Seluruh
aturan — sesi, alur, pengaduan, kewenangan — ada di Laravel, supaya tidak ada
dua tempat yang bisa saling melenceng.

## Menjalankan untuk pengujian

```bash
cd bot
export LARAVEL_URL=http://localhost:8010
export BOT_INTERNAL_TOKEN=...          # sama persis dengan .env Laravel
export TELEGRAM_BOT_TOKEN=...          # dari @BotFather
export BOT_MEDIA_ROOT=../storage/app/private

python3 -m app poll
```

Tanpa memasang apa pun. Mode `poll` dan `drain` hanya memakai pustaka bawaan
Python — bagian yang harus berjalan tanpa ditunggui lebih mudah dipercaya bila
tidak punya ketergantungan sama sekali.

**Mengapa polling untuk pengembangan:** Telegram tidak bisa menjangkau laptop.
Menyiapkan tunnel hanya untuk mencoba satu perubahan menu adalah pemborosan
waktu; `getUpdates` bekerja dari mana saja.

| Perintah | Untuk apa |
|---|---|
| `python3 -m app poll` | Telegram long polling + pengurasan outbox. Pengembangan. |
| `python3 -m app drain` | Outbox saja. |
| `python3 -m app serve` | Webhook FastAPI. Produksi — perlu `pip install -r requirements.txt`. |

## Produksi

WhatsApp **hanya** bisa webhook; Meta tidak menyediakan polling. Jadi di
produksi `serve` yang dipakai, di belakang HTTPS:

```
POST /webhook/whatsapp     ← Meta Cloud API   (tanda tangan diverifikasi)
GET  /webhook/whatsapp     ← verifikasi pendaftaran webhook
POST /webhook/telegram     ← Telegram          (header secret diperiksa)
```

Tanda tangan Meta diverifikasi **di sini**, terhadap *raw body* — hanya proses
ini yang memilikinya. Webhook tanpa tanda tangan yang sah ditolak; tanpa
`WHATSAPP_APP_SECRET` terpasang, seluruh webhook ditolak, bukan diloloskan.

## Yang sengaja tidak dilakukan

- **Verifikasi sertifikat tidak pernah dimatikan.** Koneksi ini membawa token bot dan seluruh isi percakapan warga. Bila Python tidak menemukan trust store (kejadian pada pemasang python.org di macOS), `app/tls.py` mencari milik sistem; bila tidak ada juga, layanan **menolak jalan** alih-alih berjalan tanpa verifikasi.
- **Media tidak dikirim ke Laravel sebagai byte.** Berkas ditulis ke disk privat Laravel dan yang dikirim hanyalah jalurnya, jadi foto pengaduan berada di tempat panel admin dapat menyajikannya di balik izin.
- **Update dilewati nomornya sebelum diproses.** Update yang melempar galat akan terus diambil ulang selamanya bila offset baru dimajukan setelah berhasil — satu pesan rusak akan membekukan antrean semua orang.
- **Markdown yang ditolak dikirim ulang sebagai teks biasa.** Telegram menolak seluruh pesan bila Markdown-nya tidak terurai, dan satu tanda bintang nyasar dari ketikan orang sudah cukup. Kehilangan balasan karena format adalah kekonyolan.

## Berkas

| Berkas | Isi |
|---|---|
| `app/config.py` | Setelan dari environment; tidak ada default yang kebetulan jalan |
| `app/tls.py` | Satu konteks TLS untuk semua panggilan keluar |
| `app/http.py` | Klien HTTP kecil di atas stdlib |
| `app/laravel.py` | Klien API internal: `inbound`, `outbox/pull`, `outbox/report` |
| `app/media.py` | Menulis media ke disk privat Laravel |
| `app/transports/telegram.py` | Normalisasi update, pengiriman, unduh berkas |
| `app/transports/whatsapp.py` | Cloud API: verifikasi tanda tangan, normalisasi, pengiriman |
| `app/runner.py` | Loop polling dan loop outbox |
| `app/webhook.py` | FastAPI untuk produksi |
