"""Client for the internal Laravel API.

Laravel owns every rule: sessions, the flow, complaints, who may command what.
This service only carries messages in and out, so the entire client is three
calls.
"""
from __future__ import annotations

import logging

from .config import Settings
from .http import HttpError, request_json

log = logging.getLogger("bot.laravel")


class Laravel:
    def __init__(self, settings: Settings) -> None:
        self.base = f"{settings.laravel_url}/api/bot"
        self.headers = {"X-Bot-Token": settings.internal_token}

    def config(self) -> dict:
        """Credentials as the admin panel holds them.

        Fetched rather than read from this process's own environment, so a key
        entered in the panel actually takes effect. The environment stays a
        fallback for whatever the panel does not supply.
        """
        try:
            return request_json(f"{self.base}/config", headers=self.headers)
        except HttpError as exc:
            log.warning("gagal mengambil konfigurasi (%s); memakai environment", exc.status)
            return {}

    def inbound(self, message: dict) -> list[dict]:
        """Hands one message over and returns the replies to send."""
        try:
            result = request_json(
                f"{self.base}/inbound", method="POST", payload=message, headers=self.headers
            )
        except HttpError as exc:
            # Never crash the loop for one bad message: the next person's
            # message must still be answered.
            log.error("inbound ditolak (%s): %s", exc.status, exc.body[:200])
            return []

        return result.get("messages", [])

    def pull_outbox(self, channel: str | None = None, limit: int = 20) -> list[dict]:
        payload: dict = {"limit": limit}
        if channel:
            payload["channel"] = channel

        try:
            return request_json(
                f"{self.base}/outbox/pull", method="POST", payload=payload, headers=self.headers
            ).get("messages", [])
        except HttpError as exc:
            log.error("gagal mengambil outbox (%s)", exc.status)
            return []

    def report(self, message_id: int, status: str, error: str | None = None) -> None:
        try:
            request_json(
                f"{self.base}/outbox/report",
                method="POST",
                payload={"id": message_id, "status": status, "error": error},
                headers=self.headers,
            )
        except HttpError as exc:
            # The row stays claimed and is released by its own timeout rather
            # than being lost; losing the report is better than a crash loop.
            log.error("gagal melaporkan pengiriman %s (%s)", message_id, exc.status)
