"""Mendaftarkan alamat webhook ke Telegram dan Meta setiap layanan naik.

Ini pasangan dari :mod:`app.tunnel`. Mengetahui alamat publik tidak ada gunanya
bila alamat itu masih harus ditempel manusia ke dua dasbor setiap kali stack
dinyalakan ulang — dan itulah persis bentuk kegagalan yang paling mahal di sini,
sebab tidak ada yang tampak rusak dari sisi mana pun: bot berjalan, panel
normal, dan pesan warga jatuh ke alamat yang sudah lenyap.

Telegram dapat diurus sepenuhnya: ``setWebhook`` hanya memerlukan token bot,
yang sudah ada.

WhatsApp memerlukan **App ID**, yang tidak ikut diisi orang saat memasang. Itu
pun tidak ditanyakan: access token WhatsApp tahu milik aplikasi mana dirinya,
jadi App ID-nya ditanyakan langsung kepada Meta lewat ``debug_token``. Satu
kolom yang tidak perlu diisi adalah satu kolom yang tidak dapat salah ketik —
dan salah ketiknya hanya terlihat sebagai webhook yang tidak pernah terdaftar.

Bila betul-betul tidak dapat ditentukan, alamatnya tetap dicetak utuh di log
supaya masih ada yang bisa disalin sekali ke dasbor Meta — diam bukan pilihan.
Verify tokennya tidak ikut dicetak: log container terlalu sering ditempel apa
adanya ke tiket atau grup.
"""
from __future__ import annotations

import logging
import threading
import time
import urllib.parse

from .http import HttpError, request_json
from .tunnel import public_url

log = logging.getLogger("bot.webhooks")

#: Yang benar-benar dipakai. Meminta lebih banyak berarti menerima kiriman yang
#: hanya untuk dibuang, dan setiap kiriman itu tetap harus diverifikasi.
TELEGRAM_UPDATES = ["message", "edited_message", "callback_query"]

#: Sesering apa alamat tunnel diperiksa ulang selagi layanan berjalan.
#:
#: cloudflared punya `restart: unless-stopped` sendiri: ia dapat mati dan hidup
#: kembali dengan nama baru sementara bot tidak ikut mati sama sekali. Tanpa
#: pemeriksaan ini, satu-satunya gejalanya adalah bot yang tiba-tiba berhenti
#: menerima pesan tanpa satu baris pun log yang berubah.
WATCH_SECONDS = 60

#: Meta hanya memberi enam detik bagi alamat callback untuk menjawab, dan
#: quick tunnel yang baru naik kerap melampauinya pada permintaan pertama.
#: Kegagalan seperti itu sekilas tampak seperti salah konfigurasi, padahal
#: cukup dicoba lagi sedetik kemudian.
VERIFY_ATTEMPTS = 3
VERIFY_PAUSE_SECONDS = 3.0


def publish(settings, stop: threading.Event | None = None, ready: threading.Event | None = None) -> dict:
    """Menentukan alamat publik lalu mendaftarkannya ke tiap saluran.

    Dipanggil dari thread tersendiri pada mode ``serve``. Menunggu ``ready``
    lebih dulu bila diberikan: Meta memverifikasi alamatnya dengan segera
    memanggilnya kembali, jadi mendaftar sebelum server menerima koneksi berarti
    memastikan verifikasinya gagal.
    """
    if ready is not None:
        ready.wait()

    base = public_url(settings, stop)

    if not base:
        log.info("Alamat publik tidak diketahui; pendaftaran webhook dilewati.")
        return {}

    log.info("Alamat publik layanan: %s", base)

    results = _register(settings, base)

    # Alamat yang berasal dari tunnel diawasi; alamat tetap tidak perlu.
    if stop is not None and not settings.public_url:
        _watch(settings, stop, base)

    return results


def _register(settings, base: str) -> dict:
    results: dict[str, str] = {}

    if settings.telegram_token:
        results["telegram"] = _telegram(settings, base)

    if settings.whatsapp_token:
        results["whatsapp"] = _whatsapp(settings, base)

    return results


def _watch(settings, stop: threading.Event, base: str, pause: float = WATCH_SECONDS) -> str:
    """Mendaftarkan ulang bila tunnel berganti nama selagi layanan berjalan.

    Memeriksa, bukan menunggu diberi tahu: cloudflared tidak mengabari siapa
    pun ketika namanya berubah, dan bot yang diam-diam terputus terlihat persis
    sama dengan bot yang tidak sedang ditanyai orang.
    """
    while not stop.wait(pause):
        current = public_url(settings, stop, attempts=1, pause=0)

        if not current or current == base:
            continue

        log.info("Alamat tunnel berubah: %s → %s", base, current)
        base = current
        _register(settings, base)

    return base


def _telegram(settings, base: str) -> str:
    url = f"{base}/api/webhook/telegram"
    payload: dict = {
        "url": url,
        "allowed_updates": TELEGRAM_UPDATES,
        # Pesan yang menumpuk selama layanan mati tetap dikirimkan. Warga yang
        # melapor saat server sedang turun tidak seharusnya kehilangan
        # laporannya hanya karena waktunya kebetulan buruk.
        "drop_pending_updates": False,
    }

    # Hanya disertakan bila memang ada: Telegram menolak secret_token kosong,
    # dan rahasia ini opsional justru karena Telegram tidak menandatangani
    # webhooknya sama sekali.
    if settings.telegram_secret:
        payload["secret_token"] = settings.telegram_secret

    try:
        request_json(
            f"https://api.telegram.org/bot{settings.telegram_token}/setWebhook",
            method="POST",
            payload=payload,
        )
    except (HttpError, OSError) as exc:
        log.error("Telegram menolak pendaftaran webhook: %s", exc)
        log.error("Daftarkan sendiri bila perlu: %s", url)

        return f"gagal: {exc}"

    log.info("Telegram: webhook diarahkan ke %s", url)

    return url


def _app_id(settings) -> str | None:
    """App ID dari panel, atau ditanyakan sendiri kepada Meta.

    Access token WhatsApp tahu milik aplikasi mana dirinya: ``debug_token``
    mengembalikan ``app_id`` pemiliknya. Menanyakannya jauh lebih baik daripada
    meminta orang mengisi satu kolom lagi — kolom yang, bila salah ketik satu
    angka, gagalnya hanya terlihat sebagai webhook yang tidak pernah terdaftar.
    """
    if settings.whatsapp_app_id:
        return settings.whatsapp_app_id

    try:
        data = request_json(
            f"https://graph.facebook.com/{settings.whatsapp_version}/debug_token"
            f"?input_token={urllib.parse.quote(settings.whatsapp_token)}",
            headers={"Authorization": f"Bearer {settings.whatsapp_token}"},
        )["data"]
    except (HttpError, OSError, KeyError) as exc:
        log.warning("App ID tidak dapat ditanyakan ke Meta: %s", exc)

        return None

    app_id = str(data.get("app_id") or "").strip()

    if app_id:
        log.info("App ID ditemukan dari access token: %s (%s)", app_id, data.get("application") or "?")

    return app_id or None


def _whatsapp(settings, base: str) -> str:
    url = f"{base}/api/webhook/whatsapp"
    app_id = _app_id(settings)

    if not (app_id and settings.whatsapp_app_secret):
        # Dicetak mencolok, bukan dibiarkan lewat: inilah satu-satunya hal yang
        # memisahkan stack yang menyala dari bot WhatsApp yang benar-benar
        # menerima pesan.
        log.warning("=" * 72)
        log.warning("WhatsApp: App ID tidak diketahui, webhook tidak didaftarkan sendiri.")
        log.warning("Tempel alamat ini di Meta → App → WhatsApp → Configuration:")
        log.warning("  Callback URL : %s", url)
        # Nilainya sengaja tidak ikut dicetak. Log container sering ditempel
        # apa adanya ke tiket atau grup ketika ada yang bertanya kenapa botnya
        # diam, dan verify token adalah kunci: siapa pun yang memegangnya dapat
        # mendaftarkan alamatnya sendiri sebagai penerima webhook nomor ini.
        log.warning("  Verify Token : lihat panel → Chatbot → WhatsApp")
        log.warning("")
        log.warning("Agar tidak perlu ditempel ulang setiap kali stack naik, isi App ID")
        log.warning("di panel → Chatbot → WhatsApp (Meta → App → Settings → Basic).")
        log.warning("=" * 72)

        return "manual"

    params = urllib.parse.urlencode({
        "object": "whatsapp_business_account",
        "callback_url": url,
        "verify_token": settings.whatsapp_verify_token or "",
        "fields": "messages",
    })

    endpoint = (
        f"https://graph.facebook.com/{settings.whatsapp_version}"
        f"/{app_id}/subscriptions?{params}"
    )
    headers = {
        # Token aplikasi lewat header, bukan query string: alamat lengkap
        # berakhir di log proxy mana pun yang dilewatinya, dan token ini
        # memegang seluruh aplikasi Meta.
        "Authorization": f"Bearer {app_id}|{settings.whatsapp_app_secret}",
    }

    last: Exception | None = None

    for attempt in range(1, VERIFY_ATTEMPTS + 1):
        # Tunnel dipanaskan lebih dulu.
        #
        # Meta memverifikasi alamatnya dengan memanggilnya kembali, dan hanya
        # memberi waktu enam detik. Permintaan PERTAMA yang melewati sebuah
        # quick tunnel yang baru naik kerap lebih lama dari itu — sesudahnya
        # 0,2 detik. Satu panggilan dari sini menanggung keterlambatan itu,
        # sehingga yang dihadapi Meta sudah jalur yang hangat.
        _warm(url)

        try:
            request_json(endpoint, method="POST", headers=headers)
        except (HttpError, OSError) as exc:
            last = exc
            log.warning("Pendaftaran webhook WhatsApp gagal (percobaan %s): %s", attempt, exc)
            time.sleep(VERIFY_PAUSE_SECONDS)

            continue

        log.info("WhatsApp: webhook diarahkan ke %s", url)

        return url

    log.error("Meta menolak pendaftaran webhook setelah %s percobaan: %s", VERIFY_ATTEMPTS, last)
    log.error("Tempel sendiri di dasbor Meta:")
    log.error("  Callback URL : %s", url)
    log.error("  Verify Token : lihat panel → Chatbot → WhatsApp")

    return f"gagal: {last}"


def _warm(url: str) -> None:
    """Sekali ketuk ke alamat sendiri, kegagalannya tidak penting."""
    try:
        request_json(url, timeout=10)
    except Exception:  # noqa: BLE001 - tujuannya menghangatkan, bukan memeriksa
        pass
