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

    def send(
        self,
        destination: str,
        body: str,
        media_path: str | None = None,
        template: dict | None = None,
    ) -> None:
        if template and template.get("name"):
            # Satu-satunya cara menghubungi nomor yang belum pernah menyapa
            # bot ini. Cloud API menolak teks bebas di luar 24 jam sejak pesan
            # terakhir orang itu; template yang sudah disetujui Meta tidak
            # terikat jendela tersebut — dan berbayar.
            self._post({
                "messaging_product": "whatsapp",
                "to": destination,
                "type": "template",
                "template": {
                    "name": template["name"],
                    "language": {"code": template.get("language") or "id"},
                    "components": [{
                        "type": "body",
                        "parameters": [
                            {"type": "text", "text": str(value)}
                            for value in template.get("parameters", [])
                        ],
                    }] if template.get("parameters") else [],
                },
            })
            return

        if media_path:
            media_id = self._upload(media_path)

            if media_id:
                # Caption dibatasi 1024 karakter oleh Cloud API; melebihinya
                # membuat SELURUH pesan ditolak, bukan captionnya dipotong.
                payload = {
                    "messaging_product": "whatsapp",
                    "to": destination,
                    "type": "image",
                    "image": {"id": media_id, "caption": (body or "")[:1024]},
                }

                self._post(payload)
                return

            # Unggahan gagal: kabarnya tetap berangkat sebagai teks. Petugas
            # yang menerima laporan tanpa foto masih bisa bekerja; petugas yang
            # tidak menerima apa pun tidak.
            log.warning("gagal mengunggah %s; dikirim sebagai teks", media_path)

        self._post({
            "messaging_product": "whatsapp",
            "to": destination,
            "type": "text",
            "text": {"preview_url": False, "body": body or "…"},
        })

    def _post(self, payload: dict) -> dict:
        return request_json(
            f"{GRAPH}/{self.version}/{self.phone_id}/messages",
            method="POST",
            payload=payload,
            headers={"Authorization": f"Bearer {self.token}"},
        )

    def _upload(self, media_path: str) -> str | None:
        """Menyerahkan berkas ke Meta dan mengembalikan id-nya.

        Cloud API tidak menerima berkas pada panggilan kirim: ia harus
        diunggah lebih dulu, lalu dikirim dengan id hasilnya. Berbeda dengan
        Telegram yang menerima keduanya sekaligus.

        Mengembalikan None bila berkasnya tidak ada atau Meta menolaknya —
        pemanggilnya lalu mengirim teks saja, bukan tidak mengirim apa pun.
        """
        path = self.media_root / media_path

        if not path.exists():
            log.warning("berkas tidak ditemukan: %s", path)
            return None

        boundary = "----botform"
        mime = "image/png" if path.suffix.lower() == ".png" else "image/jpeg"
        parts: list[bytes] = []

        for name, value in (("messaging_product", "whatsapp"), ("type", mime)):
            parts.append(
                f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode()
            )

        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{path.name}"\r\n'
            f"Content-Type: {mime}\r\n\r\n".encode()
        )
        parts.append(path.read_bytes())
        parts.append(f"\r\n--{boundary}--\r\n".encode())

        import json
        import urllib.request

        request = urllib.request.Request(
            f"{GRAPH}/{self.version}/{self.phone_id}/media",
            data=b"".join(parts),
            headers={
                "Authorization": f"Bearer {self.token}",
                "Content-Type": f"multipart/form-data; boundary={boundary}",
            },
            method="POST",
        )

        try:
            with urllib.request.urlopen(request, timeout=60, context=CONTEXT) as response:
                return json.load(response).get("id")
        except Exception as exc:  # noqa: BLE001
            log.warning("unggahan ditolak Meta: %s", exc)
            return None

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
