"""Webhook mode, for production.

The only mode WhatsApp supports: Meta does not offer polling. Telegram works
either way, and is registered here too so one deployment serves both.

Both endpoints do the same three things — prove the request came from the
platform, normalise it, hand it to Laravel — and answer 200 quickly, because a
slow reply makes the platform retry a delivery that is already being handled.

Berkas ini sengaja TIDAK memakai `from __future__ import annotations`, tidak
seperti modul lain di layanan ini.

Baris itu menjadikan seluruh anotasi sebagai teks, dan FastAPI menyelesaikannya
terhadap global modul — sedangkan `Request` di-import di dalam `build_app()`
agar fastapi hanya dibutuhkan pada mode `serve`. Akibatnya FastAPI tidak
mengenali `request: Request` sebagai objek permintaan, memperlakukannya sebagai
parameter query yang wajib, dan menjawab **setiap** webhook dengan 422 —
verifikasi pendaftaran Meta maupun pesan masuk. Python 3.11 tidak memerlukan
baris itu untuk sintaks `str | None` yang dipakai di bawah.
"""
import json
import logging
import threading
import time

from .config import Settings
from .laravel import Laravel
from .transports.telegram import Telegram
from .transports.whatsapp import WhatsApp

log = logging.getLogger("bot.webhook")


class Channels:
    """Kredensial saluran, yang dapat diambil ulang tanpa menjalankan ulang bot.

    Kunci dibaca sekali saat bot dijalankan — itu sederhana dan benar untuk
    hampir segalanya. Yang tidak dilayaninya adalah rotasi: App Secret yang
    diganti di panel membuat bot menolak setiap pesan sungguhan sampai
    seseorang ingat me-restart-nya, dan tidak ada apa pun di sisi Meta yang
    menjelaskan mengapa.
    """

    #: Sekali per jeda ini, sekalipun tanda tangan gagal beruntun.
    COOLDOWN_SECONDS = 30

    def __init__(self, settings: Settings, laravel: Laravel) -> None:
        self._base = settings
        self._laravel = laravel
        self._whatsapp = WhatsApp(settings)
        self._refreshed_at = 0.0

    def whatsapp(self) -> WhatsApp:
        return self._whatsapp

    def refresh_whatsapp(self) -> WhatsApp:
        now = time.monotonic()

        if now - self._refreshed_at < self.COOLDOWN_SECONDS:
            return self._whatsapp

        self._refreshed_at = now

        try:
            remote = self._laravel.config()
        except Exception:  # noqa: BLE001
            return self._whatsapp

        if remote and remote.get("channels"):
            self._whatsapp = WhatsApp(self._base.merged(remote))
            log.info("Kredensial WhatsApp diambil ulang dari panel admin.")

        return self._whatsapp


def build_app(settings: Settings):
    from fastapi import APIRouter, FastAPI, Header, HTTPException, Request, Response

    app = FastAPI(title="Layanan bot", docs_url=None, redoc_url=None)

    # Rutenya dikumpulkan di router, bukan langsung di app, supaya dapat
    # dipasang dua kali — lihat include_router di bawah.
    router = APIRouter()

    laravel = Laravel(settings)
    channels = Channels(settings, laravel)
    telegram = Telegram(settings) if settings.telegram_token else None

    @router.get("/health")
    def health() -> dict:
        return {"status": "ok"}

    @router.get("/webhook/whatsapp")
    def verify(request: Request) -> Response:
        """Meta echoes a challenge back when the webhook is registered."""
        params = request.query_params

        if (
            params.get("hub.mode") == "subscribe"
            and settings.whatsapp_verify_token
            and params.get("hub.verify_token") == settings.whatsapp_verify_token
        ):
            return Response(content=params.get("hub.challenge", ""), media_type="text/plain")

        # Permintaan tanpa parameter hub.* sama sekali bukan upaya verifikasi:
        # Meta selalu mengirim hub.mode, hub.verify_token, dan hub.challenge
        # sekaligus. Yang tiba polos seperti ini adalah orang yang membuka
        # alamatnya di browser untuk memastikan layanannya hidup.
        #
        # Menjawabnya 403 "Verifikasi gagal" membuat pemasangan yang sehat
        # tampak rusak, dan waktu terbuang mencari kerusakan yang tidak ada.
        # Menjawabnya ramah tidak melonggarkan apa pun: pemeriksaan verify
        # token di atas tidak tersentuh, dan tidak ada keterangan rahasia yang
        # ikut keluar — tidak juga apakah verify token sudah disetel.
        if not any(key.startswith("hub.") for key in params):
            return Response(
                content=json.dumps({
                    "status": "ok",
                    "endpoint": "webhook WhatsApp",
                    "keterangan": "Titik ini hanya melayani Meta. Verifikasi memerlukan parameter hub.*.",
                }, ensure_ascii=False),
                media_type="application/json",
            )

        # Sampai di sini berarti parameter hub.* ada tetapi tidak cocok: itu
        # upaya verifikasi yang benar-benar gagal, dan tetap ditolak.
        raise HTTPException(status_code=403, detail="Verifikasi gagal.")

    @router.post("/webhook/whatsapp")
    async def whatsapp_inbound(request: Request, x_hub_signature_256: str | None = Header(default=None)) -> dict:
        raw = await request.body()

        if not channels.whatsapp().verify_signature(raw, x_hub_signature_256):
            # Tanda tangan yang gagal bisa berarti dua hal: pesannya bukan dari
            # Meta, atau kunci kita sudah usang. Kunci dibaca sekali saat bot
            # dijalankan, jadi App Secret yang dirotasi di panel membuat bot
            # menolak SETIAP pesan sungguhan sampai seseorang me-restart-nya —
            # dan dari sisi Meta kegagalannya tidak terlihat sama sekali.
            #
            # Jadi sebelum menolak, kunci diambil ulang sekali lalu diperiksa
            # lagi. Ada jeda agar banjir tanda tangan palsu tidak berubah
            # menjadi banjir permintaan ke Laravel.
            if not channels.refresh_whatsapp().verify_signature(raw, x_hub_signature_256):
                # Unsigned means not from Meta, whatever the body claims.
                raise HTTPException(status_code=401, detail="Tanda tangan tidak sah.")

        whatsapp = channels.whatsapp()
        body = await request.json()

        for entry in body.get("entry", []):
            for message in whatsapp.normalise(entry):
                for reply in laravel.inbound(message):
                    try:
                        whatsapp.send(message["from"], reply.get("body", ""), reply.get("media_path"))
                    except Exception as exc:  # noqa: BLE001
                        log.warning("gagal membalas WhatsApp: %s", exc)

        return {"status": "ok"}

    @router.post("/webhook/telegram")
    async def telegram_inbound(
        request: Request,
        x_telegram_bot_api_secret_token: str | None = Header(default=None),
    ) -> dict:
        if not telegram:
            raise HTTPException(status_code=503, detail="Telegram belum dikonfigurasi.")

        # Telegram does not sign its bodies, so the shared secret it echoes back
        # is the only thing distinguishing a real update from anyone's POST.
        if settings.telegram_secret and x_telegram_bot_api_secret_token != settings.telegram_secret:
            raise HTTPException(status_code=401, detail="Secret token tidak cocok.")

        update = await request.json()
        payload = telegram.normalise(update)

        if payload:
            for reply in laravel.inbound(payload):
                try:
                    telegram.send(payload["from"], reply.get("body", ""), reply.get("media_path"))
                except Exception as exc:  # noqa: BLE001
                    log.warning("gagal membalas Telegram: %s", exc)

        return {"status": "ok"}

    # Dipasang dua kali dengan sengaja: /webhook/... dan /api/webhook/...
    #
    # Alamat yang sudah didaftarkan ke Meta tidak selalu dapat diubah dengan
    # murah — pendaftaran ulang berarti menyentuh dasbor Meta, dan sebuah
    # webhook yang salah path hanya terlihat sebagai 404 yang sunyi di sisi
    # sana. Melayani keduanya menghapus satu kelas kegagalan itu sepenuhnya,
    # dengan biaya satu baris.
    app.include_router(router)
    app.include_router(router, prefix="/api")

    return app


def serve(settings: Settings, ready: "threading.Event | None" = None) -> int:
    import uvicorn

    app = build_app(settings)

    # Ditandai dari dalam server, bukan ditebak dengan menunggu sekian detik.
    #
    # Pendaftaran webhook menunggu tanda ini karena Meta memverifikasi alamat
    # dengan segera memanggilnya kembali: mendaftar sebelum server menerima
    # koneksi berarti memastikan verifikasinya gagal — dan gagalnya sunyi.
    if ready is not None:
        app.add_event_handler("startup", ready.set)

    uvicorn.run(app, host="0.0.0.0", port=int(__import__("os").environ.get("BOT_PORT", "8020")))

    return 0
