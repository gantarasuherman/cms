"""The two loops that keep the bot alive.

`poll` brings messages in when no public URL exists — which is every laptop.
`drain` carries out whatever Laravel queued, chiefly the notification to
officers when a complaint arrives. They run together so one process is enough
for development.
"""
from __future__ import annotations

import logging
import threading
import time

from .config import Settings
from .laravel import Laravel
from .transports.telegram import Telegram
from .transports.whatsapp import WhatsApp

log = logging.getLogger("bot.runner")


def poll_telegram(settings: Settings, stop: threading.Event) -> None:
    telegram = Telegram(settings)
    laravel = Laravel(settings)
    offset = 0

    log.info("Telegram: menunggu pesan (long polling)…")

    while not stop.is_set():
        try:
            updates = telegram.updates(offset, settings.poll_timeout)
        except Exception as exc:  # noqa: BLE001
            # A network blip must not end the loop; back off and try again.
            log.warning("gagal mengambil update: %s", exc)
            stop.wait(5)
            continue

        for update in updates:
            # Advanced before handling, not after: an update that throws would
            # otherwise be fetched again forever and never let the next one
            # through.
            offset = max(offset, update.get("update_id", 0) + 1)

            try:
                payload = telegram.normalise(update)

                if not payload:
                    continue

                for reply in laravel.inbound(payload):
                    telegram.send(payload["from"], reply.get("body", ""), reply.get("media_path"))
            except Exception as exc:  # noqa: BLE001
                log.exception("gagal memproses update %s: %s", update.get("update_id"), exc)


def drain_outbox(settings: Settings, stop: threading.Event) -> None:
    laravel = Laravel(settings)

    transports = {}

    if settings.telegram_token:
        transports["telegram"] = Telegram(settings)

    whatsapp = WhatsApp(settings)
    if whatsapp.configured():
        transports["whatsapp"] = whatsapp

    log.info("Outbox: mengirim antrean setiap %s detik (%s)", settings.outbox_interval, ", ".join(transports) or "tidak ada kanal")

    while not stop.is_set():
        for message in laravel.pull_outbox():
            transport = transports.get(message.get("channel"))

            if not transport:
                laravel.report(message["id"], "failed", f"Kanal {message.get('channel')} tidak dikonfigurasi di layanan bot.")
                continue

            try:
                # Template hanya dikenal WhatsApp; Telegram tidak punya
                # padanannya dan tidak membutuhkannya — ia tidak mengenal
                # jendela 24 jam sama sekali.
                if message.get("template") and hasattr(transport, "key") and transport.key == "whatsapp":
                    transport.send(
                        message["destination"],
                        message.get("body", ""),
                        message.get("media_path"),
                        message.get("template"),
                    )
                else:
                    transport.send(message["destination"], message.get("body", ""), message.get("media_path"))
                laravel.report(message["id"], "sent")
            except Exception as exc:  # noqa: BLE001
                # Reported as failed so Laravel can retry with its own widening
                # delay rather than this loop inventing one.
                log.warning("gagal mengirim #%s: %s", message["id"], exc)
                laravel.report(message["id"], "failed", str(exc)[:400])

        stop.wait(settings.outbox_interval)
