"""WhatsApp through Meta's Cloud API.

The official route, chosen over an unofficial web client because that risks the
number being banned permanently and ties delivery to a phone staying online.

Webhook only: Meta does not offer polling. The signature is verified here, over
the raw body, because only this process ever sees it.
"""
from __future__ import annotations

import hashlib
import hmac
import logging
from pathlib import Path

from ..config import Settings
from ..tls import CONTEXT
from ..http import download, request_json
from ..media import store

log = logging.getLogger("bot.whatsapp")

GRAPH = "https://graph.facebook.com"


class WhatsApp:
    key = "whatsapp"

    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.token = settings.whatsapp_token
        self.phone_id = settings.whatsapp_phone_id
        self.version = settings.whatsapp_version
        self.media_root: Path = settings.media_root

    def configured(self) -> bool:
        return bool(self.token and self.phone_id)

    # ------------------------------------------------------------ security

    def verify_signature(self, raw_body: bytes, header: str | None) -> bool:
        """Meta signs every webhook body. An unsigned request is not from Meta."""
        secret = self.settings.whatsapp_app_secret

        if not secret:
            # Refuse rather than wave it through: an unverified webhook is an
            # open endpoint anyone can post complaints to.
            log.error("WHATSAPP_APP_SECRET belum diisi; webhook ditolak.")
            return False

        if not header or not header.startswith("sha256="):
            return False

        expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()

        return hmac.compare_digest(expected, header.split("=", 1)[1])

    # ----------------------------------------------------------- outgoing

    def send(self, destination: str, body: str, media_path: str | None = None) -> None:
        payload = {
            "messaging_product": "whatsapp",
            "to": destination,
            "type": "text",
            "text": {"preview_url": False, "body": body or "…"},
        }

        request_json(
            f"{GRAPH}/{self.version}/{self.phone_id}/messages",
            method="POST",
            payload=payload,
            headers={"Authorization": f"Bearer {self.token}"},
        )

    # ----------------------------------------------------------- incoming

    def normalise(self, entry: dict) -> list[dict]:
        """A Cloud API webhook body carries several messages; this flattens it."""
        out: list[dict] = []

        for change in entry.get("changes", []):
            value = change.get("value", {})
            contacts = {c.get("wa_id"): c for c in value.get("contacts", [])}

            for message in value.get("messages", []):
                out.append(self._one(message, contacts))

        return out

    def _one(self, message: dict, contacts: dict) -> dict:
        sender = message.get("from")
        profile = contacts.get(sender, {}).get("profile", {})
        kind = message.get("type", "text")

        payload: dict = {
            "channel": self.key,
            "external_id": f"wa:{message.get('id')}",
            "from": sender,
            "type": "text",
            "text": None,
            "sender_name": profile.get("name"),
            "raw": {"timestamp": message.get("timestamp"), "type": kind},
        }

        if kind == "text":
            payload["text"] = message.get("text", {}).get("body")
        elif kind == "location":
            location = message.get("location", {})
            payload.update({
                "type": "location",
                "latitude": location.get("latitude"),
                "longitude": location.get("longitude"),
            })
        elif kind in {"image", "document", "audio", "video", "sticker"}:
            payload["text"] = message.get(kind, {}).get("caption")
            payload.update(self._fetch(message.get(kind, {}).get("id"), kind))
        elif kind == "interactive":
            # A tapped button answers exactly as typing its number would.
            interactive = message.get("interactive", {})
            payload["text"] = (
                interactive.get("button_reply", {}).get("id")
                or interactive.get("list_reply", {}).get("id")
            )
        else:
            payload["type"] = kind

        return payload

    def _fetch(self, media_id: str | None, kind: str) -> dict:
        if not media_id or not self.token:
            return {"type": kind}

        try:
            info = request_json(
                f"{GRAPH}/{self.version}/{media_id}",
                headers={"Authorization": f"Bearer {self.token}"},
            )
            url = info.get("url")

            if not url:
                return {"type": kind}

            import urllib.request

            req = urllib.request.Request(url, headers={"Authorization": f"Bearer {self.token}"})
            with urllib.request.urlopen(req, timeout=60, context=CONTEXT) as response:
                body = response.read()
                content_type = response.headers.get("Content-Type", "")

            stored = store(self.media_root, body, content_type, "bin")
        except Exception as exc:  # noqa: BLE001
            log.warning("gagal mengunduh media WhatsApp: %s", exc)
            return {"type": kind}

        return {"type": kind, "media_path": stored, "media_mime": content_type.split(";")[0] or None}
