"""Command line for the bot service.

    python -m app poll     # Telegram long polling + outbox (pengembangan)
    python -m app drain    # outbox saja
    python -m app serve    # webhook FastAPI (produksi)
"""
from __future__ import annotations

import logging
import signal
import sys
import threading
import time

from .config import Settings
from .runner import drain_outbox, poll_telegram


#: Jeda antar percobaan menanyai panel saat layanan baru naik.
CONFIG_PAUSE_SECONDS = 2.0

#: Sesering apa penantian itu dilaporkan ke log, dalam hitungan percobaan.
CONFIG_REPORT_EVERY = 30

#: 0 berarti menunggu tanpa batas — lihat alasannya di _await_config.
CONFIG_ATTEMPTS = 0


def _await_config(laravel, attempts: int = CONFIG_ATTEMPTS, pause: float = CONFIG_PAUSE_SECONDS):
    """Kredensial dari panel, ditunggu sampai panelnya benar-benar menjawab.

    Bot hampir selalu siap lebih dulu daripada nginx, dan sekali kalah cepat
    ia dulu berjalan SELAMANYA tanpa kredensial: tidak bisa memverifikasi
    tanda tangan, tidak bisa mendaftarkan webhook, tidak bisa membalas — tanpa
    satu pun tanda bahwa ada yang rusak, sebab prosesnya sendiri sehat dan
    lognya diam. Satu-satunya gejalanya "botnya tidak jalan".

    Yang ditunggu hanya ketidakmampuan menanyai panelnya. Jawaban kosong
    adalah jawaban: pemasangan baru yang belum dikonfigurasi harus tetap naik,
    bukan tertahan pada setiap kali start.

    Ditunggu tanpa batas, dan itu disengaja. Batas waktu pernah dipasang di
    sini — enam puluh detik — dan akibatnya persis seperti cacat yang hendak
    diperbaikinya: satu kali stack naik dengan nginx yang lambat, bot menyerah
    lalu berjalan berjam-jam tanpa kredensial. Tanpa kredensial ia tidak dapat
    memverifikasi tanda tangan webhook, tidak dapat mendaftarkan alamatnya,
    dan tidak dapat menguras outbox — berjalan hanya berarti berpura-pura.
    Menunggu jauh lebih jujur, dan penantiannya berakhir sendiri begitu Laravel
    menjawab.

    `attempts=0` berarti tanpa batas; nilai lain dipakai pengujian.
    """
    log = logging.getLogger("bot")
    attempt = 0

    while attempts == 0 or attempt < attempts:
        attempt += 1
        remote = laravel.config()

        if remote is not None:
            if attempt > 1:
                log.info("Panel menjawab setelah %s percobaan.", attempt)

            return remote

        if attempt == 1 or attempt % CONFIG_REPORT_EVERY == 0:
            log.warning(
                "Panel belum dapat ditanyai (percobaan %s). Layanan menunggu: "
                "tanpa kredensial, bot tidak dapat menerima maupun mengirim apa pun.",
                attempt,
            )

        time.sleep(pause)

    log.error("Panel tidak dapat ditanyai setelah %s percobaan.", attempts)

    return None


def main() -> int:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)-7s %(name)s  %(message)s",
        datefmt="%H:%M:%S",
    )

    command = sys.argv[1] if len(sys.argv) > 1 else "poll"
    settings = Settings.load()

    # Credentials entered in the admin panel take precedence over this
    # process's environment, so changing a key there needs only a restart.
    from .laravel import Laravel

    remote = _await_config(Laravel(settings))

    if remote and remote.get("channels"):
        settings = settings.merged(remote)
        logging.getLogger("bot").info(
            "Kredensial diambil dari panel admin: %s", ", ".join(remote["channels"])
        )

    stop = threading.Event()

    def shutdown(*_: object) -> None:
        logging.getLogger("bot").info("Berhenti…")
        stop.set()

    signal.signal(signal.SIGINT, shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    if command == "serve":
        # Only the webhook mode needs a web server, so the dependency is only
        # imported when that mode is actually asked for.
        from .webhook import serve

        # Outbox tetap dikuras pada mode webhook.
        #
        # Webhook hanya mengurus pesan yang MASUK. Segala yang keluar tanpa
        # diminta — kabar pengaduan baru kepada petugas, balasan yang ditulis
        # operator dari panel — menunggu di outbox dan hanya berangkat lewat
        # loop ini. Tanpa itu `serve` menjawab warga dengan baik sementara
        # petugas tidak pernah dikabari sama sekali, dan tidak ada yang
        # terlihat rusak dari sisi mana pun: pengaduannya tercatat rapi di
        # panel, barisnya menumpuk diam-diam di bot_outbox.
        threading.Thread(target=drain_outbox, args=(settings, stop), daemon=True).start()

        # Alamat webhook didaftarkan sendiri, tidak ditempel manusia.
        #
        # Quick tunnel Cloudflare memberi nama acak yang baru setiap container
        # dibuat ulang, jadi alamat yang kemarin didaftarkan ke Meta hari ini
        # sudah lenyap — dan tidak ada yang tampak rusak: bot berjalan, panel
        # normal, pesan warga jatuh ke alamat yang tidak ada lagi. Menunggu
        # `ready` karena Meta memverifikasi alamatnya dengan segera
        # memanggilnya kembali.
        from .webhooks import publish

        ready = threading.Event()
        threading.Thread(target=publish, args=(settings, stop, ready), daemon=True).start()

        return serve(settings, ready)

    threads: list[threading.Thread] = []

    if command in {"poll", "all"}:
        if not settings.telegram_token:
            logging.getLogger("bot").error("TELEGRAM_BOT_TOKEN belum diisi.")
            return 1

        threads.append(threading.Thread(target=poll_telegram, args=(settings, stop), daemon=True))

    threads.append(threading.Thread(target=drain_outbox, args=(settings, stop), daemon=True))

    for thread in threads:
        thread.start()

    while not stop.is_set():
        stop.wait(1)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
