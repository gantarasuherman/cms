ARG PYTHON_VERSION=3.11
FROM python:${PYTHON_VERSION}-slim

# Mode `poll` dan `drain` sengaja tidak punya ketergantungan sama sekali, tapi
# `serve` — satu-satunya mode yang dimengerti WhatsApp — butuh FastAPI. Satu
# image untuk ketiganya lebih sederhana daripada dua image yang nyaris sama,
# dan requirements.txt tetap menjadi satu-satunya daftar versi.
COPY bot/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt \
    && rm /tmp/requirements.txt

COPY laradock/bot/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Kode bot datang lewat bind mount, sama seperti kode Laravel di service lain,
# supaya perubahan cukup dengan restart dan tidak perlu build ulang.
WORKDIR /var/www/bot

# Python menulis log lewat buffer bila keluarannya bukan terminal; tanpa ini
# `docker compose logs bot` diam saja sampai buffer penuh.
ENV PYTHONUNBUFFERED=1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
