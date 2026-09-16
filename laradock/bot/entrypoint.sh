#!/bin/sh
# Memilih mode jalan bot, supaya `docker compose up` tidak pernah menghasilkan
# container yang mati berulang-ulang.
#
# `python -m app poll` berhenti dengan kode 1 bila token Telegram belum ada —
# di environment maupun di panel admin. Dipasang apa adanya, pemasangan baru
# yang belum sempat mengisi kredensial akan restart terus tanpa henti. Maka
# mode bawaan `auto`: menguras outbox bila belum ada saluran, dan naik sendiri
# ke long polling begitu tokennya terisi.
set -eu

log() { printf '%s  bot-entrypoint  %s\n' "$(date '+%H:%M:%S')" "$*" >&2; }

if [ -z "${BOT_INTERNAL_TOKEN:-}" ]; then
    log "BOT_INTERNAL_TOKEN kosong. Layanan bot tidak dijalankan tanpa itu:"
    log "  isi di .env aplikasi, nilainya dari"
    log "  php -r 'echo bin2hex(random_bytes(32));'"
    exit 1
fi

MODE="${BOT_MODE:-auto}"

if [ "$MODE" = "auto" ]; then
    # Penentuannya butuh jawaban Laravel, jadi tunggu dulu sampai ia menyahut.
    MODE="$(python - <<'PY'
import logging
import os
import sys
import time
import urllib.error
import urllib.request

logging.disable(logging.CRITICAL)  # keputusan mode bukan urusan log bot

base = os.environ.get("LARAVEL_URL", "http://nginx").rstrip("/")


def laravel_awake() -> bool:
    """Sudah ada yang menjawab di alamat itu?

    Status apa pun berarti Laravel hidup — 401 dari middleware bot.token pun
    sudah cukup. Yang ditunggu hanyalah koneksinya, bukan izinnya.
    """
    try:
        urllib.request.urlopen(f"{base}/api/bot/config", timeout=5)
        return True
    except urllib.error.HTTPError:
        return True
    except Exception:
        return False


for _ in range(60):
    if laravel_awake():
        break
    time.sleep(2)
else:
    # Laravel tidak kunjung siap. `drain` tidak merusak apa pun bila
    # dijalankan tanpa pekerjaan, jadi tetap jalan dan coba lagi sendiri.
    print("drain")
    sys.exit(0)

try:
    from app.config import Settings
    from app.laravel import Laravel

    settings = Settings.load()
    channels = (Laravel(settings).config().get("channels") or {})
    telegram = channels.get("telegram") or {}
    has_telegram = bool(telegram.get("token") or settings.telegram_token)
except Exception:
    has_telegram = False

print("poll" if has_telegram else "drain")
PY
)"

    if [ "$MODE" = "poll" ]; then
        log "Token Telegram ditemukan — mode poll (long polling + outbox)."
    else
        log "Belum ada token Telegram. Mode drain: outbox saja."
        log "Isi token di panel admin lalu: docker compose restart bot"
    fi
fi

log "Menjalankan: python -m app $MODE"
exec python -m app "$MODE"
