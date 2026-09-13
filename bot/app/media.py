"""Writes media a platform sent into Laravel's private disk.

Laravel is handed a path, never the bytes: the two processes share the
directory, and a complaint's photograph must live where the admin panel can
serve it under permission.
"""
from __future__ import annotations

import logging
import secrets
from pathlib import Path

log = logging.getLogger("bot.media")

EXTENSIONS = {
    "image/jpeg": "jpg",
    "image/jpg": "jpg",
    "image/png": "png",
    "image/webp": "webp",
    "image/gif": "gif",
    "audio/ogg": "ogg",
    "audio/mpeg": "mp3",
    "video/mp4": "mp4",
    "application/pdf": "pdf",
}

MAX_BYTES = 20 * 1024 * 1024


def store(root: Path, body: bytes, content_type: str, fallback_ext: str = "bin") -> str | None:
    """Returns the path relative to the disk root, as Laravel stores it."""
    if not body or len(body) > MAX_BYTES:
        log.warning("berkas dilewati: kosong atau terlalu besar (%s byte)", len(body))
        return None

    mime = (content_type or "").split(";")[0].strip().lower()
    extension = EXTENSIONS.get(mime, fallback_ext)

    relative = f"bot/{secrets.token_hex(16)}.{extension}"
    target = root / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(body)

    return relative
