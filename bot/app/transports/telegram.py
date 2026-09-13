"""Telegram Bot API.

Two ways in, one way out. In production a webhook is registered and Telegram
posts updates; in development the same normalising code is driven by long
polling, because Telegram cannot reach a laptop and setting up a tunnel to try
a menu change is a poor use of anyone's afternoon.
"""
from __future__ import annotations

import logging
from pathlib import Path

from ..config import Settings
from ..tls import CONTEXT
from ..http import HttpError, download, request_json
from ..media import store

log = logging.getLogger("bot.telegram")

API = "https://api.telegram.org"

MIME_BY_EXTENSION = {
    "jpg": "image/jpeg",
    "jpeg": "image/jpeg",
    "png": "image/png",
    "webp": "image/webp",
    "gif": "image/gif",
    "mp4": "video/mp4",
    "ogg": "audio/ogg",
    "oga": "audio/ogg",
    "mp3": "audio/mpeg",
    "pdf": "application/pdf",
}


class Telegram:
    key = "telegram"

    def __init__(self, settings: Settings) -> None:
        if not settings.telegram_token:
            raise RuntimeError("TELEGRAM_BOT_TOKEN belum diisi.")

        self.settings = settings
        self.token = settings.telegram_token
        self.media_root: Path = settings.media_root
        # Who has already been looked up in this process, so a chatty
        # conversation does not fetch the same picture on every turn.
        self._avatars_seen: set[str] = set()

    # ----------------------------------------------------------- outgoing

    def send(self, destination: str, body: str, media_path: str | None = None) -> None:
        if media_path:
            self._send_photo(destination, body, media_path)
            return

        payload = {"chat_id": destination, "text": body or "…", "parse_mode": "Markdown"}

        try:
            self._call("sendMessage", payload)
        except HttpError as exc:
            # Telegram rejects the whole message when its Markdown does not
            # parse — a stray asterisk in something a person typed is enough.
            # Losing the reply over formatting would be absurd, so it is sent
            # again as plain text.
            if exc.status == 400:
                log.warning("Markdown ditolak, dikirim ulang sebagai teks biasa")
                payload.pop("parse_mode")
                self._call("sendMessage", payload)
            else:
                raise

    def _send_photo(self, destination: str, caption: str, media_path: str) -> None:
        path = self.media_root / media_path

        if not path.exists():
            self._call("sendMessage", {"chat_id": destination, "text": caption or "…"})
            return

        # multipart by hand rather than a dependency, since this is the only
        # place in the service that needs it.
        boundary = "----botform"
        parts: list[bytes] = []

        for name, value in (("chat_id", destination), ("caption", caption or "")):
            parts.append(
                f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode()
            )

        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="{path.name}"\r\n'
            f"Content-Type: application/octet-stream\r\n\r\n".encode()
        )
        parts.append(path.read_bytes())
        parts.append(f"\r\n--{boundary}--\r\n".encode())

        import urllib.request

        req = urllib.request.Request(
            f"{API}/bot{self.token}/sendPhoto",
            data=b"".join(parts),
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}"},
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=60, context=CONTEXT):
            pass

    def _call(self, method: str, payload: dict) -> dict:
        return request_json(f"{API}/bot{self.token}/{method}", method="POST", payload=payload)

    # ----------------------------------------------------------- incoming

    def updates(self, offset: int, timeout: int) -> list[dict]:
        """Long poll. Returns raw updates."""
        result = request_json(
            f"{API}/bot{self.token}/getUpdates",
            method="POST",
            payload={"offset": offset, "timeout": timeout, "allowed_updates": ["message"]},
            timeout=timeout + 15,
        )
        return result.get("result", [])

    def profile_photo(self, user_id: int | str) -> tuple[str, str] | None:
        """The person's profile picture, stored on the shared private disk.

        Telegram offers this; the WhatsApp Cloud API does not expose profile
        pictures at all, so those contacts keep their initials in the panel.

        Returns (path, file_id) so Laravel can skip the write when the picture
        has not changed. A failure here is silent: a missing avatar is a set of
        initials, not a broken conversation.
        """
        try:
            info = request_json(
                f"{API}/bot{self.token}/getUserProfilePhotos",
                method="POST",
                payload={"user_id": user_id, "limit": 1},
            )
            photos = info.get("result", {}).get("photos") or []

            if not photos or not photos[0]:
                return None

            # Sizes ascend; the second-largest is plenty for a 40px avatar and
            # a fraction of the bytes.
            sizes = photos[0]
            chosen = sizes[min(1, len(sizes) - 1)]
            file_id = chosen.get("file_id")

            if not file_id:
                return None

            file = request_json(f"{API}/bot{self.token}/getFile", method="POST", payload={"file_id": file_id})
            path = file.get("result", {}).get("file_path")

            if not path:
                return None

            body, content_type = download(f"{API}/file/bot{self.token}/{path}")
            stored = store(self.media_root, body, content_type or "image/jpeg", "jpg")

            return (stored, file_id) if stored else None
        except Exception as exc:  # noqa: BLE001
            log.debug("tidak dapat mengambil foto profil: %s", exc)
            return None

    def normalise(self, update: dict) -> dict | None:
        """One Telegram update as the Laravel API expects it, or None to skip."""
        message = update.get("message") or update.get("edited_message")

        if not message:
            return None

        chat = message.get("chat", {})
        sender = message.get("from", {})
        chat_id = str(chat.get("id"))

        payload: dict = {
            "channel": self.key,
            # Message ids restart per chat, so the chat is part of the key or
            # two people would collide and the second be treated as a replay.
            "external_id": f"tg:{chat_id}:{message.get('message_id')}",
            "from": chat_id,
            "type": "text",
            "text": message.get("text") or message.get("caption"),
            "sender_name": " ".join(
                filter(None, [sender.get("first_name"), sender.get("last_name")])
            )
            or chat.get("title"),
            "sender_username": sender.get("username"),
            "raw": {"update_id": update.get("update_id"), "chat_type": chat.get("type")},
        }

        # Fetched once per person, not per message: the id is remembered on the
        # contact and the same one is skipped next time.
        if sender.get("id") and chat_id not in self._avatars_seen:
            self._avatars_seen.add(chat_id)

            if photo := self.profile_photo(sender["id"]):
                payload["sender_avatar_path"], payload["sender_avatar_ref"] = photo

        if location := message.get("location"):
            payload.update(
                {"type": "location", "latitude": location.get("latitude"), "longitude": location.get("longitude")}
            )
        elif photos := message.get("photo"):
            # Telegram sends every size; the last is the largest.
            payload.update(self._fetch(photos[-1].get("file_id"), "image", "jpg"))
        elif document := message.get("document"):
            payload.update(self._fetch(document.get("file_id"), "document", "bin"))
        elif voice := message.get("voice"):
            payload.update(self._fetch(voice.get("file_id"), "audio", "ogg"))
        elif message.get("sticker"):
            payload["type"] = "sticker"
        elif message.get("contact"):
            payload["type"] = "contact"
        elif message.get("video"):
            payload["type"] = "video"

        return payload

    def _fetch(self, file_id: str | None, kind: str, fallback_ext: str) -> dict:
        if not file_id:
            return {"type": kind}

        try:
            info = request_json(f"{API}/bot{self.token}/getFile", method="POST", payload={"file_id": file_id})
            file_path = info.get("result", {}).get("file_path")

            if not file_path:
                return {"type": kind}

            body, content_type = download(f"{API}/file/bot{self.token}/{file_path}")

            # Telegram serves files as application/octet-stream, so the type is
            # taken from the name it gave the file. A photograph recorded as
            # octet-stream is downloaded by a browser instead of shown.
            if not content_type or content_type.startswith("application/octet-stream"):
                content_type = MIME_BY_EXTENSION.get(
                    file_path.rsplit(".", 1)[-1].lower() if "." in file_path else "",
                    content_type,
                )

            stored = store(self.media_root, body, content_type, fallback_ext)
        except Exception as exc:  # noqa: BLE001
            # A failed download is a message without its picture, not a dead
            # conversation: the flow will simply say the photo is still missing.
            log.warning("gagal mengunduh berkas: %s", exc)
            return {"type": kind}

        return {"type": kind, "media_path": stored, "media_mime": content_type.split(";")[0] or None}
