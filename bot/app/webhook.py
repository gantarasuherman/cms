"""Webhook mode, for production.

The only mode WhatsApp supports: Meta does not offer polling. Telegram works
either way, and is registered here too so one deployment serves both.

Both endpoints do the same three things — prove the request came from the
platform, normalise it, hand it to Laravel — and answer 200 quickly, because a
slow reply makes the platform retry a delivery that is already being handled.
"""
from __future__ import annotations

import logging

from .config import Settings
from .laravel import Laravel
from .transports.telegram import Telegram
from .transports.whatsapp import WhatsApp

log = logging.getLogger("bot.webhook")


def build_app(settings: Settings):
    from fastapi import FastAPI, Header, HTTPException, Request, Response

    app = FastAPI(title="Layanan bot", docs_url=None, redoc_url=None)
    laravel = Laravel(settings)
    whatsapp = WhatsApp(settings)
    telegram = Telegram(settings) if settings.telegram_token else None

    @app.get("/health")
    def health() -> dict:
        return {"status": "ok"}

    @app.get("/webhook/whatsapp")
    def verify(request: Request) -> Response:
        """Meta echoes a challenge back when the webhook is registered."""
        params = request.query_params

        if (
            params.get("hub.mode") == "subscribe"
            and settings.whatsapp_verify_token
            and params.get("hub.verify_token") == settings.whatsapp_verify_token
        ):
            return Response(content=params.get("hub.challenge", ""), media_type="text/plain")

        raise HTTPException(status_code=403, detail="Verifikasi gagal.")

    @app.post("/webhook/whatsapp")
    async def whatsapp_inbound(request: Request, x_hub_signature_256: str | None = Header(default=None)) -> dict:
        raw = await request.body()

        if not whatsapp.verify_signature(raw, x_hub_signature_256):
            # Unsigned means not from Meta, whatever the body claims.
            raise HTTPException(status_code=401, detail="Tanda tangan tidak sah.")

        body = await request.json()

        for entry in body.get("entry", []):
            for message in whatsapp.normalise(entry):
                for reply in laravel.inbound(message):
                    try:
                        whatsapp.send(message["from"], reply.get("body", ""), reply.get("media_path"))
                    except Exception as exc:  # noqa: BLE001
                        log.warning("gagal membalas WhatsApp: %s", exc)

        return {"status": "ok"}

    @app.post("/webhook/telegram")
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

    return app


def serve(settings: Settings) -> int:
    import uvicorn

    uvicorn.run(build_app(settings), host="0.0.0.0", port=int(__import__("os").environ.get("BOT_PORT", "8020")))

    return 0
