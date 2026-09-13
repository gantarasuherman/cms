"""Command line for the bot service.

    python -m app poll     # Telegram long polling + outbox (pengembangan)
    python -m app drain    # outbox saja
    python -m app serve    # webhook FastAPI (produksi)
"""
from __future__ import annotations

import logging
import signal
import sys
import threading

from .config import Settings
from .runner import drain_outbox, poll_telegram


def main() -> int:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)-7s %(name)s  %(message)s",
        datefmt="%H:%M:%S",
    )

    command = sys.argv[1] if len(sys.argv) > 1 else "poll"
    settings = Settings.load()

    # Credentials entered in the admin panel take precedence over this
    # process's environment, so changing a key there needs only a restart.
    from .laravel import Laravel

    remote = Laravel(settings).config()

    if remote.get("channels"):
        settings = settings.merged(remote)
        logging.getLogger("bot").info(
            "Kredensial diambil dari panel admin: %s", ", ".join(remote["channels"])
        )

    stop = threading.Event()

    def shutdown(*_: object) -> None:
        logging.getLogger("bot").info("Berhenti…")
        stop.set()

    signal.signal(signal.SIGINT, shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    if command == "serve":
        # Only the webhook mode needs a web server, so the dependency is only
        # imported when that mode is actually asked for.
        from .webhook import serve

        return serve(settings)

    threads: list[threading.Thread] = []

    if command in {"poll", "all"}:
        if not settings.telegram_token:
            logging.getLogger("bot").error("TELEGRAM_BOT_TOKEN belum diisi.")
            return 1

        threads.append(threading.Thread(target=poll_telegram, args=(settings, stop), daemon=True))

    threads.append(threading.Thread(target=drain_outbox, args=(settings, stop), daemon=True))

    for thread in threads:
        thread.start()

    while not stop.is_set():
        stop.wait(1)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
