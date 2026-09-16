# Laradock — Dynamic CMS

Stack pengembangan berbasis Docker. Seluruh port dapat diubah lewat `laradock/.env`
sehingga tidak bentrok dengan project lain di mesin yang sama.

## Layanan

| Service      | Gambar / basis        | Port host (bawaan) | Keterangan |
|--------------|-----------------------|--------------------|------------|
| `nginx`      | `nginx:alpine`        | **8010** → 80      | Melayani `public/` |
| `php-fpm`    | `php:8.3-fpm`         | —                  | Menjalankan PHP untuk nginx |
| `workspace`  | `php:8.3-cli` + Node 20 | **5173** → 5173  | artisan, composer, npm, Vite |
| `mysql`      | `mysql:8.0`           | **33066** → 3306   | Data di volume `mysql-data` |
| `redis`      | `redis:7-alpine`      | **63790** → 6379   | Cache & session |
| `queue`      | = workspace           | —                  | `php artisan queue:work` |
| `scheduler`  | = workspace           | —                  | `php artisan schedule:work` |
| `bot`        | `python:3.11-slim`    | **8011** → 8020    | Jembatan WhatsApp/Telegram |
| `phpmyadmin` | `phpmyadmin:latest`   | **8082** → 80      | Profil `tools` saja |

Versi PHP (8.3) dan Node (20) diatur lewat `PHP_VERSION` dan `NODE_VERSION` di `.env`.

## Menjalankan pertama kali

```bash
cd laradock
cp .env.example .env          # ubah port di sini bila bentrok
docker compose up -d --build  # build pertama memakan beberapa menit
```

Siapkan aplikasi dari dalam `workspace`:

```bash
docker compose exec workspace composer install
docker compose exec workspace cp -n .env.example .env
docker compose exec workspace php artisan key:generate
docker compose exec workspace php artisan migrate --seed
docker compose exec workspace php artisan storage:link
docker compose exec workspace npm install
docker compose exec workspace npm run build
```

Buka **http://localhost:8010** — admin panel di `/admin/login`.

## Konfigurasi `.env` aplikasi

Di dalam jaringan Docker, yang berlaku adalah **nama service dan port aslinya**,
bukan port host:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql          # bukan 127.0.0.1
DB_PORT=3306           # bukan 33066
DB_DATABASE=cms
DB_USERNAME=cms
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Menjalankan `artisan` dari host (bukan dari `workspace`)? Pakai `DB_HOST=127.0.0.1`
dan `DB_PORT=33066`.

## Chatbot

Asisten AI **bukan service tersendiri** — ia berjalan di dalam Laravel
(`config/ai.php`), jadi `php-fpm` dan `queue` sudah menanggungnya. Mati secara
bawaan; nyalakan dan isi kuncinya dari panel admin.

Yang punya container sendiri hanyalah jembatan Python di `bot/`: ia menerima
pesan dari WhatsApp/Telegram, meneruskannya ke API internal Laravel, lalu
mengirim balasan yang dikembalikan.

`BOT_INTERNAL_TOKEN` dibaca dari **`.env` aplikasi**, berkas yang sama dengan
yang dibaca Laravel — bukan disalin ke `laradock/.env`. Dua salinan rahasia
yang sama adalah dua nilai yang cepat atau lambat berbeda.

### Mode

Diatur lewat `BOT_MODE` di `laradock/.env`:

| Mode | Perilaku |
|---|---|
| `auto` (bawaan) | Menguras outbox saja bila belum ada kredensial; naik sendiri ke long polling Telegram begitu token terisi |
| `poll` | Paksa long polling Telegram |
| `drain` | Outbox saja |
| `serve` | Webhook FastAPI di port **8011** |

`auto` ada karena `python -m app poll` berhenti dengan kode 1 bila token
Telegram belum ada. Dipasang apa adanya, pemasangan baru yang belum sempat
mengisi kredensial akan restart tanpa henti.

Sudah mengisi token di panel admin? `docker compose restart bot` — mode dipilih
ulang saat start.

### WhatsApp

Meta hanya mendukung webhook; tidak ada polling. Jadi WhatsApp memerlukan
`BOT_MODE=serve` di belakang HTTPS publik:

```
POST /webhook/whatsapp     ← Meta Cloud API (tanda tangan diverifikasi)
GET  /webhook/whatsapp     ← verifikasi pendaftaran
POST /webhook/telegram     ← Telegram (header secret diperiksa)
```

Dari localhost, Meta tidak bisa menjangkau port 8011 — perlu tunnel
(`cloudflared`, `ngrok`) yang diarahkan ke sana. Telegram tidak punya masalah
ini: `auto`/`poll` bekerja dari mana saja tanpa tunnel.

Pada mode `serve`, alamat tunnel itu **tidak perlu ditempel dengan tangan**.
Service bot menanyakannya ke metrics cloudflared (`http://cloudflared:20241/quicktunnel`)
setiap kali naik, lalu mendaftarkannya sendiri ke Telegram — dan ke Meta juga
bila App ID terisi. Isi `BOT_PUBLIC_URL` untuk melewati seluruh langkah itu
ketika alamatnya memang sudah tetap.

## Vite

```bash
docker compose exec workspace npm run dev
```

`vite.config.js` mengikat server ke `0.0.0.0` agar dapat dijangkau dari host, dan
HMR tetap menunjuk `localhost` supaya browser terhubung ke port yang benar.

## Perintah harian

```bash
docker compose exec workspace php artisan migrate
docker compose exec workspace php artisan test
docker compose exec workspace npm run build
docker compose logs -f nginx php-fpm
docker compose --profile tools up -d      # phpMyAdmin di :8082
docker compose down                       # data tetap aman di volume
docker compose down -v                    # HAPUS data MySQL dan Redis
```

## Bila port bentrok

Ubah nilainya di `laradock/.env` lalu `docker compose up -d`. Tidak ada port yang
ditulis langsung di `docker-compose.yml`.
