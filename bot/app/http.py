"""A very small HTTP client.

The standard library rather than `requests`, deliberately: the polling loop and
the outbox drain are the parts that must keep running unattended, and they are
easier to trust with no dependencies at all. FastAPI is only pulled in for the
production webhook mode, where a real server is genuinely needed.
"""
from __future__ import annotations

import json
import logging
import urllib.error
import urllib.parse
import urllib.request

from .tls import CONTEXT

log = logging.getLogger("bot.http")


class HttpError(Exception):
    def __init__(self, status: int, body: str) -> None:
        super().__init__(f"HTTP {status}: {body[:300]}")
        self.status = status
        self.body = body


def request_json(
    url: str,
    *,
    method: str = "GET",
    payload: dict | None = None,
    headers: dict[str, str] | None = None,
    timeout: int = 30,
) -> dict:
    data = None
    headers = dict(headers or {})

    if payload is not None:
        data = json.dumps(payload).encode()
        headers["Content-Type"] = "application/json"

    headers.setdefault("Accept", "application/json")

    req = urllib.request.Request(url, data=data, headers=headers, method=method)

    try:
        with urllib.request.urlopen(req, timeout=timeout, context=CONTEXT) as response:
            body = response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        raise HttpError(exc.code, exc.read().decode("utf-8", "replace")) from exc

    return json.loads(body) if body else {}


def download(url: str, timeout: int = 60) -> tuple[bytes, str]:
    """Returns the body and its content type."""
    with urllib.request.urlopen(url, timeout=timeout, context=CONTEXT) as response:
        return response.read(), response.headers.get("Content-Type", "")
