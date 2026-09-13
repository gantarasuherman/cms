"""One TLS context for every outgoing call.

Some Python builds — notably the python.org installers on macOS — ship with no
certificate bundle configured at all, so `ssl.get_default_verify_paths().cafile`
is None and every HTTPS call fails with "self signed certificate in certificate
chain". The fix is to point verification at the operating system's own trust
store, which is what curl already uses on the same machine.

What this deliberately does NOT do is stop verifying. These connections carry
the bot token and everything the public writes to it; accepting any certificate
would mean accepting anyone who can sit between this service and the platform.
If no trust store can be found the service refuses to start rather than
continuing without one.
"""
from __future__ import annotations

import logging
import os
import ssl
from pathlib import Path

log = logging.getLogger("bot.tls")

# The usual locations, most specific first. macOS, then the common Linux
# distributions a container might be built on.
CANDIDATES = (
    "/etc/ssl/cert.pem",
    "/etc/ssl/certs/ca-certificates.crt",
    "/etc/pki/tls/certs/ca-bundle.crt",
    "/usr/local/etc/openssl/cert.pem",
)


def _bundle() -> str | None:
    if configured := os.environ.get("SSL_CERT_FILE"):
        return configured

    if ssl.get_default_verify_paths().cafile:
        return None  # Python already knows where to look.

    try:
        import certifi  # noqa: PLC0415
    except ImportError:
        pass
    else:
        return certifi.where()

    for candidate in CANDIDATES:
        if Path(candidate).is_file():
            log.info("Memakai trust store sistem: %s", candidate)
            return candidate

    return None


def context() -> ssl.SSLContext:
    bundle = _bundle()

    ctx = ssl.create_default_context(cafile=bundle)

    if not (bundle or ssl.get_default_verify_paths().cafile or ctx.get_ca_certs()):
        raise RuntimeError(
            "Tidak ditemukan sertifikat CA untuk memverifikasi koneksi HTTPS. "
            "Setel SSL_CERT_FILE ke berkas bundle CA, atau pasang paket certifi. "
            "Layanan tidak dijalankan tanpa verifikasi sertifikat."
        )

    return ctx


CONTEXT = context()
