"""Alamat publik layanan ini, sebagaimana dilihat dunia luar.

Meta tidak menyediakan polling: WhatsApp hanya berbicara lewat webhook, dan
webhook memerlukan satu alamat HTTPS publik yang tetap. Dari sebuah laptop,
alamat itu datang dari tunnel.

Persoalannya, *quick tunnel* Cloudflare — yang dipakai stack ini karena tidak
memerlukan akun maupun domain — memberi nama acak yang **baru setiap kali
container dibuat ulang**. Alamat yang kemarin didaftarkan ke Meta hari ini
sudah tidak ada, dan tidak ada apa pun yang tampak rusak: bot berjalan, panel
normal, Meta mengirim ke alamat yang sudah lenyap.

Modul ini menutup celah itu dengan menanyakan alamatnya kepada cloudflared
sendiri, bukan kepada manusia. Dua sumber, berurutan:

1.  ``BOT_PUBLIC_URL`` — dipakai apa adanya bila diisi. Inilah yang dipakai
    ketika alamatnya memang tetap: domain sendiri, named tunnel Cloudflare,
    atau domain statis ngrok. Tidak ada yang perlu ditebak.

2.  Metrics cloudflared — ``GET /quicktunnel`` mengembalikan
    ``{"hostname": "…trycloudflare.com"}``. Ditanyakan berulang kali karena
    bot biasanya siap lebih dulu daripada tunnelnya.

Log cloudflared sengaja TIDAK dibaca. Mengambil alamat dengan mencocokkan pola
pada teks log berarti bergantung pada kalimat yang boleh berubah kapan saja di
rilis berikutnya, dan kegagalannya sunyi.
"""
from __future__ import annotations

import logging
import threading

from .http import request_json

log = logging.getLogger("bot.tunnel")

#: Cukup lama untuk tunnel yang lambat naik, cukup pendek untuk menyerah
#: sebelum orang berhenti menunggu dan membuka dasbor Meta sendiri.
ATTEMPTS = 60
PAUSE_SECONDS = 2.0


def _probes(settings) -> list:
    """Sumber alamat yang akan dicoba, berurutan, pada setiap percobaan.

    ngrok lebih dulu, dan itu disengaja: cloudflared selalu ikut `docker compose
    up`, sedangkan ngrok hanya menyala bila seseorang memintanya dengan profil
    `tunnel`. Menyalakannya adalah pernyataan bahwa alamat itulah yang
    dikehendaki — biasanya karena domainnya tetap dan sudah tertulis di dasbor
    Meta. Mendahulukan cloudflared akan diam-diam mengabaikan permintaan itu.

    Dicoba bergantian dalam satu putaran, bukan satu sumber sampai habis: yang
    tidak dipakai gagal seketika (namanya tidak ada di jaringan Docker), jadi
    urutan ini tidak memperlambat apa pun.
    """
    probes = []

    if settings.ngrok_api:
        probes.append(("ngrok", lambda: _ngrok(settings.ngrok_api)))

    if settings.tunnel_metrics:
        probes.append(("cloudflared", lambda: _cloudflared(settings.tunnel_metrics)))

    return probes


def _cloudflared(base: str) -> str | None:
    hostname = (request_json(base.rstrip("/") + "/quicktunnel", timeout=5).get("hostname") or "").strip()

    return "https://" + hostname.strip("/") if hostname else None


def _ngrok(base: str) -> str | None:
    """Alamat publik dari agen ngrok yang sedang berjalan.

    Hanya yang HTTPS. Agen ngrok lama membuka http dan https sekaligus untuk
    satu tunnel, dan Meta menolak callback yang bukan HTTPS — mengambil yang
    pertama saja berarti kadang-kadang mendaftarkan alamat yang pasti ditolak.
    """
    for tunnel in request_json(base.rstrip("/") + "/api/tunnels", timeout=5).get("tunnels", []):
        url = (tunnel.get("public_url") or "").strip()

        if url.startswith("https://"):
            return url.rstrip("/")

    return None


def public_url(
    settings,
    stop: threading.Event | None = None,
    attempts: int = ATTEMPTS,
    pause: float = PAUSE_SECONDS,
) -> str | None:
    """Alamat HTTPS publik layanan ini, atau None bila tidak dapat ditentukan.

    None bukan kesalahan: sebuah pemasangan di server sungguhan dengan domain
    sendiri tidak punya tunnel untuk ditanyai, dan memang tidak memerlukannya.
    """
    if settings.public_url:
        return settings.public_url.rstrip("/")

    if not (settings.ngrok_api or settings.tunnel_metrics):
        return None

    for attempt in range(1, attempts + 1):
        if stop is not None and stop.is_set():
            return None

        for name, probe in _probes(settings):
            try:
                found = probe()
            except Exception as exc:  # noqa: BLE001 - tunnel belum naik, bukan cacat
                found = None
                if attempt == 1:
                    log.info("Menunggu %s siap: %s", name, exc)

            if found:
                return found

        if stop is not None:
            stop.wait(pause)
        else:
            import time

            time.sleep(pause)

    log.warning(
        "Alamat tunnel tidak terbaca setelah %s percobaan. "
        "Isi BOT_PUBLIC_URL bila alamatnya memang tetap.",
        attempts,
    )

    return None
