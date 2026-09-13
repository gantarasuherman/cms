"""Runtime settings, read from the environment.

The bot service holds the platform credentials and the shared secret for the
Laravel API. Nothing here has a default that would work by accident: a missing
token raises at start-up rather than producing a service that runs and silently
answers nobody.
"""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


def _require(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(
            f"{name} belum diisi. Layanan bot tidak dijalankan tanpa kredensial lengkap."
        )
    return value


@dataclass(frozen=True)
class Settings:
    laravel_url: str
    internal_token: str
    media_root: Path
    telegram_token: str | None
    telegram_secret: str | None
    whatsapp_token: str | None
    whatsapp_phone_id: str | None
    whatsapp_verify_token: str | None
    whatsapp_app_secret: str | None
    whatsapp_version: str
    poll_timeout: int
    outbox_interval: int

    def merged(self, remote: dict) -> "Settings":
        """This process's settings, with whatever the panel supplied on top.

        The panel wins where it has a value; the environment fills the rest. A
        deployment that configured everything in `.env` keeps working, and one
        that configures nothing there works as soon as somebody fills the form.
        """
        channels = remote.get("channels", {})
        telegram = channels.get("telegram", {})
        whatsapp = channels.get("whatsapp", {})

        from dataclasses import replace

        return replace(
            self,
            telegram_token=telegram.get("token") or self.telegram_token,
            telegram_secret=telegram.get("secret_token") or self.telegram_secret,
            whatsapp_token=whatsapp.get("token") or self.whatsapp_token,
            whatsapp_phone_id=whatsapp.get("phone_number_id") or self.whatsapp_phone_id,
            whatsapp_verify_token=whatsapp.get("verify_token") or self.whatsapp_verify_token,
            whatsapp_app_secret=whatsapp.get("app_secret") or self.whatsapp_app_secret,
        )

    @classmethod
    def load(cls) -> "Settings":
        return cls(
            laravel_url=os.environ.get("LARAVEL_URL", "http://localhost:8010").rstrip("/"),
            internal_token=_require("BOT_INTERNAL_TOKEN"),
            # Laravel's private disk. Both processes must see the same
            # directory, or a photograph the bot downloads is a broken link in
            # the admin panel.
            media_root=Path(os.environ.get("BOT_MEDIA_ROOT", "storage/app/private")).resolve(),
            telegram_token=os.environ.get("TELEGRAM_BOT_TOKEN") or None,
            telegram_secret=os.environ.get("TELEGRAM_WEBHOOK_SECRET") or None,
            whatsapp_token=os.environ.get("WHATSAPP_ACCESS_TOKEN") or None,
            whatsapp_phone_id=os.environ.get("WHATSAPP_PHONE_NUMBER_ID") or None,
            whatsapp_verify_token=os.environ.get("WHATSAPP_VERIFY_TOKEN") or None,
            whatsapp_app_secret=os.environ.get("WHATSAPP_APP_SECRET") or None,
            whatsapp_version=os.environ.get("WHATSAPP_GRAPH_VERSION", "v21.0"),
            poll_timeout=int(os.environ.get("BOT_POLL_TIMEOUT", "30")),
            outbox_interval=int(os.environ.get("BOT_OUTBOX_INTERVAL", "5")),
        )
