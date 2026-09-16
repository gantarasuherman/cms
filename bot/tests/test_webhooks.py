"""Tests untuk alamat publik dan pendaftarannya.

Bagian ini dijaga ketat karena kegagalannya selalu sunyi. Salah alamat, salah
path, atau mendaftar sebelum server siap tidak memunculkan error di mana pun:
bot berjalan, panel normal, dan pesan warga jatuh ke tempat yang tidak ada.
Satu-satunya gejalanya adalah "botnya tidak membalas", yang bisa berarti apa
saja.

    cd bot && python3 -m unittest discover tests
"""
from __future__ import annotations

import os
import sys
import threading
import unittest
from dataclasses import replace
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

os.environ.setdefault("BOT_INTERNAL_TOKEN", "uji")

from app import tunnel, webhooks  # noqa: E402
from app.config import Settings  # noqa: E402
from app.http import HttpError  # noqa: E402


def settings(**overrides) -> Settings:
    base = replace(
        Settings.load(),
        telegram_token="123:uji",
        telegram_secret=None,
        whatsapp_token="EAA-uji",
        whatsapp_phone_id="1261256213745703",
        whatsapp_verify_token="kata-sandi-verifikasi",
        whatsapp_app_secret="rahasia-aplikasi",
        whatsapp_app_id=None,
        public_url=None,
        tunnel_metrics=None,
        ngrok_api=None,
    )

    return replace(base, **overrides)


class Recorder:
    """Pengganti request_json yang mencatat panggilan alih-alih mengirimnya."""

    def __init__(self, *results) -> None:
        self.calls: list[dict] = []
        self.results = list(results) or [{}]

    def __call__(self, url, *, method="GET", payload=None, headers=None, timeout=30):
        self.calls.append({"url": url, "method": method, "payload": payload, "headers": headers or {}})
        result = self.results[min(len(self.calls) - 1, len(self.results) - 1)]

        if isinstance(result, Exception):
            raise result

        return result


class PublicAddress(unittest.TestCase):
    def test_a_fixed_address_wins_and_asks_nobody(self) -> None:
        recorder = Recorder()
        tunnel.request_json = recorder

        found = tunnel.public_url(settings(
            public_url="https://bot.contoh.go.id/",
            tunnel_metrics="http://cloudflared:20241",
        ))

        self.assertEqual("https://bot.contoh.go.id", found)
        # Alamat yang sudah pasti tidak perlu dikonfirmasi ke tunnel, dan
        # sebuah pemasangan dengan domain sendiri sering tidak punya tunnel
        # untuk ditanyai sama sekali.
        self.assertEqual([], recorder.calls)

    def test_the_tunnel_is_asked_for_its_current_name(self) -> None:
        recorder = Recorder({"hostname": "acak-sekali.trycloudflare.com"})
        tunnel.request_json = recorder

        found = tunnel.public_url(settings(tunnel_metrics="http://cloudflared:20241/"))

        self.assertEqual("https://acak-sekali.trycloudflare.com", found)
        self.assertEqual("http://cloudflared:20241/quicktunnel", recorder.calls[0]["url"])

    def test_ngrok_is_preferred_when_it_is_running(self) -> None:
        tunnel.request_json = lambda url, **kw: (
            {"tunnels": [{"public_url": "http://tetap.ngrok-free.dev"},
                         {"public_url": "https://tetap.ngrok-free.dev"}]}
            if "/api/tunnels" in url else {"hostname": "acak.trycloudflare.com"}
        )

        found = tunnel.public_url(settings(
            ngrok_api="http://ngrok:4040",
            tunnel_metrics="http://cloudflared:20241",
        ))

        # Menyalakan profil `tunnel` adalah pernyataan bahwa alamat itulah yang
        # dikehendaki — biasanya karena domainnya tetap dan sudah tertulis di
        # dasbor Meta. HTTPS-nya, bukan yang http: Meta menolak callback biasa.
        self.assertEqual("https://tetap.ngrok-free.dev", found)

    def test_cloudflared_still_answers_when_ngrok_is_absent(self) -> None:
        def probe(url, **kw):
            if "/api/tunnels" in url:
                raise OSError("Name or service not known: ngrok")
            return {"hostname": "acak.trycloudflare.com"}

        tunnel.request_json = probe

        found = tunnel.public_url(
            settings(ngrok_api="http://ngrok:4040", tunnel_metrics="http://cloudflared:20241"),
            attempts=2,
            pause=0,
        )

        # ngrok hanya menyala bila diminta; ketiadaannya adalah keadaan normal,
        # bukan kegagalan yang boleh menghentikan pencarian.
        self.assertEqual("https://acak.trycloudflare.com", found)

    def test_no_tunnel_and_no_address_is_not_an_error(self) -> None:
        # Pemasangan di server sungguhan dengan domain sendiri: tidak ada
        # tunnel untuk ditanyai, dan memang tidak diperlukan.
        self.assertIsNone(tunnel.public_url(settings()))

    def test_it_keeps_asking_while_the_tunnel_comes_up(self) -> None:
        recorder = Recorder(OSError("belum siap"), {"hostname": "akhirnya.trycloudflare.com"})
        tunnel.request_json = recorder

        found = tunnel.public_url(
            settings(tunnel_metrics="http://cloudflared:20241"),
            attempts=5,
            pause=0,
        )

        # Bot hampir selalu siap lebih dulu daripada tunnelnya; menyerah pada
        # percobaan pertama berarti hampir selalu menyerah.
        self.assertEqual("https://akhirnya.trycloudflare.com", found)
        self.assertEqual(2, len(recorder.calls))

    def test_it_gives_up_rather_than_hanging_forever(self) -> None:
        tunnel.request_json = Recorder(OSError("tidak ada tunnel"))

        self.assertIsNone(
            tunnel.public_url(settings(tunnel_metrics="http://cloudflared:20241"), attempts=2, pause=0)
        )


class TelegramRegistration(unittest.TestCase):
    def setUp(self) -> None:
        self.recorder = Recorder()
        webhooks.request_json = self.recorder

    def test_the_address_carries_the_api_prefix(self) -> None:
        webhooks._telegram(settings(), "https://contoh.trycloudflare.com")

        call = self.recorder.calls[0]

        self.assertIn("/bot123:uji/setWebhook", call["url"])
        # Path harus persis yang dilayani: sebuah webhook yang salah path hanya
        # terlihat sebagai 404 yang sunyi di sisi Telegram.
        self.assertEqual(
            "https://contoh.trycloudflare.com/api/webhook/telegram",
            call["payload"]["url"],
        )

    def test_the_secret_is_omitted_when_there_is_none(self) -> None:
        webhooks._telegram(settings(telegram_secret=None), "https://contoh.trycloudflare.com")

        # Telegram menolak secret_token kosong, dan rahasia ini memang opsional.
        self.assertNotIn("secret_token", self.recorder.calls[0]["payload"])

    def test_the_secret_is_sent_when_there_is_one(self) -> None:
        webhooks._telegram(settings(telegram_secret="rahasia"), "https://contoh.trycloudflare.com")

        self.assertEqual("rahasia", self.recorder.calls[0]["payload"]["secret_token"])

    def test_waiting_messages_are_not_thrown_away(self) -> None:
        webhooks._telegram(settings(), "https://contoh.trycloudflare.com")

        # Warga yang melapor saat layanan sedang turun tidak seharusnya
        # kehilangan laporannya hanya karena waktunya kebetulan buruk.
        self.assertIs(False, self.recorder.calls[0]["payload"]["drop_pending_updates"])

    def test_a_refusal_is_reported_not_swallowed(self) -> None:
        webhooks.request_json = Recorder(HttpError(401, "Unauthorized"))

        self.assertTrue(webhooks._telegram(settings(), "https://contoh.trycloudflare.com").startswith("gagal"))


class WhatsAppRegistration(unittest.TestCase):
    def setUp(self) -> None:
        self.recorder = Recorder()
        webhooks.request_json = self.recorder

    def subscription(self) -> dict:
        """Panggilan langganan, dipilih dari isinya.

        Bukan berdasarkan urutan: pendaftaran diawali satu ketukan pemanasan ke
        alamat sendiri, dan memilih berdasarkan indeks membuat tes ini pecah
        setiap kali urutan panggilannya berubah tanpa ada perilaku yang salah.
        """
        for call in self.recorder.calls:
            if "/subscriptions?" in call["url"]:
                return call

        raise AssertionError('Tidak ada panggilan langganan sama sekali.')

    def test_the_app_id_is_asked_of_meta_rather_than_of_a_human(self) -> None:
        self.recorder = Recorder({"data": {"app_id": "1078568481572824", "application": "BOT"}}, {})
        webhooks.request_json = self.recorder

        webhooks._whatsapp(settings(whatsapp_app_id=None), "https://contoh.trycloudflare.com")

        # Satu kolom yang tidak perlu diisi adalah satu kolom yang tidak dapat
        # salah ketik — dan salah ketiknya hanya terlihat sebagai webhook yang
        # tidak pernah terdaftar.
        self.assertIn("/debug_token?", self.recorder.calls[0]["url"])
        self.assertIn("/1078568481572824/subscriptions?", self.subscription()["url"])

    def test_the_panel_value_is_trusted_without_asking(self) -> None:
        webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        # Sudah diisi berarti sudah dijawab; menanyakannya lagi hanya satu
        # panggilan jaringan tambahan pada setiap kali layanan naik.
        self.assertFalse(any("/debug_token?" in c["url"] for c in self.recorder.calls))

    def test_the_tunnel_is_warmed_before_meta_is_asked_to_verify(self) -> None:
        webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        # Meta hanya memberi enam detik bagi alamat callback untuk menjawab,
        # dan permintaan pertama lewat quick tunnel yang baru naik kerap lebih
        # lama dari itu. Satu ketukan dari sini menanggung keterlambatan itu.
        self.assertEqual("https://contoh.trycloudflare.com/api/webhook/whatsapp", self.recorder.calls[0]["url"])

    def test_a_timed_out_verification_is_tried_again(self) -> None:
        # Gagal sekali, lalu berhasil. Kegagalan seperti ini sekilas tampak
        # seperti salah konfigurasi, padahal cukup dicoba lagi sedetik kemudian.
        self.recorder = Recorder({}, HttpError(400, "curl_errno = 28"), {}, {})
        webhooks.request_json = self.recorder
        webhooks.VERIFY_PAUSE_SECONDS = 0

        result = webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        self.assertEqual("https://contoh.trycloudflare.com/api/webhook/whatsapp", result)
        self.assertEqual(2, sum("/subscriptions?" in c["url"] for c in self.recorder.calls))

    def test_it_stops_trying_rather_than_hammering_meta(self) -> None:
        self.recorder = Recorder(HttpError(400, "curl_errno = 28"))
        webhooks.request_json = self.recorder
        webhooks.VERIFY_PAUSE_SECONDS = 0

        result = webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        self.assertTrue(result.startswith("gagal"))
        self.assertEqual(
            webhooks.VERIFY_ATTEMPTS,
            sum("/subscriptions?" in c["url"] for c in self.recorder.calls),
        )

    def test_when_the_app_id_cannot_be_found_nothing_is_guessed(self) -> None:
        self.recorder = Recorder(HttpError(401, "token tidak terbaca"))
        webhooks.request_json = self.recorder

        result = webhooks._whatsapp(settings(whatsapp_app_id=None), "https://contoh.trycloudflare.com")

        # Memanggil Graph dengan App ID karangan hanya menghasilkan penolakan
        # yang membingungkan. Alamatnya dicetak untuk ditempel dengan tangan.
        self.assertEqual("manual", result)
        self.assertEqual(1, len(self.recorder.calls))

    def test_it_subscribes_the_app_to_incoming_messages(self) -> None:
        webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        call = self.subscription()

        self.assertEqual("POST", call["method"])
        self.assertIn("/998877/subscriptions?", call["url"])
        self.assertIn("object=whatsapp_business_account", call["url"])
        self.assertIn("fields=messages", call["url"])
        self.assertIn(
            "callback_url=https%3A%2F%2Fcontoh.trycloudflare.com%2Fapi%2Fwebhook%2Fwhatsapp",
            call["url"],
        )

    def test_the_verify_token_travels_with_it(self) -> None:
        webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        # Meta memanggil balik alamatnya dan menunggu token ini dikembalikan;
        # mendaftar dengan token yang berbeda dari yang dipegang bot berarti
        # verifikasinya pasti gagal.
        self.assertIn("verify_token=kata-sandi-verifikasi", self.subscription()["url"])

    def test_the_app_token_never_lands_in_the_url(self) -> None:
        webhooks._whatsapp(settings(whatsapp_app_id="998877"), "https://contoh.trycloudflare.com")

        call = self.subscription()

        # Alamat lengkap berakhir di log proxy mana pun yang dilewatinya, dan
        # token ini memegang seluruh aplikasi Meta.
        self.assertNotIn("rahasia-aplikasi", call["url"])
        self.assertEqual("Bearer 998877|rahasia-aplikasi", call["headers"]["Authorization"])


class Publishing(unittest.TestCase):
    def setUp(self) -> None:
        self.recorder = Recorder()
        webhooks.request_json = self.recorder
        tunnel.request_json = Recorder({"hostname": "acak.trycloudflare.com"})

    def test_it_waits_until_the_server_accepts_connections(self) -> None:
        ready = threading.Event()
        done = threading.Event()

        def run() -> None:
            webhooks.publish(settings(tunnel_metrics="http://cloudflared:20241"), None, ready)
            done.set()

        threading.Thread(target=run, daemon=True).start()

        # Meta memverifikasi alamatnya dengan segera memanggilnya kembali:
        # mendaftar sebelum server menerima koneksi memastikan gagal.
        self.assertFalse(done.wait(0.2))
        self.assertEqual([], self.recorder.calls)

        ready.set()

        self.assertTrue(done.wait(5))
        self.assertTrue(self.recorder.calls)

    def test_an_unknown_address_registers_nothing(self) -> None:
        self.assertEqual({}, webhooks.publish(settings()))
        self.assertEqual([], self.recorder.calls)

    def test_a_new_tunnel_name_is_picked_up_without_a_restart(self) -> None:
        tunnel.request_json = Recorder(
            {"hostname": "lama.trycloudflare.com"},
            {"hostname": "baru.trycloudflare.com"},
        )
        stop = threading.Event()

        def stop_after_one_round() -> None:
            # Satu putaran cukup: yang diuji adalah bahwa perubahan nama
            # terdeteksi sama sekali, bukan berapa kali ia diperiksa.
            stop.wait(0.15)
            stop.set()

        threading.Thread(target=stop_after_one_round, daemon=True).start()

        final = webhooks._watch(
            settings(tunnel_metrics="http://cloudflared:20241"),
            stop,
            "https://lama.trycloudflare.com",
            pause=0.05,
        )

        # cloudflared punya restart-nya sendiri: ia dapat mati dan hidup lagi
        # dengan nama baru tanpa bot ikut mati, dan satu-satunya gejalanya
        # adalah bot yang berhenti menerima pesan tanpa satu baris log berubah.
        self.assertEqual("https://baru.trycloudflare.com", final)
        self.assertTrue(self.recorder.calls)

    def test_a_fixed_address_is_never_watched(self) -> None:
        stop = threading.Event()
        stop.set()

        webhooks.publish(settings(public_url="https://bot.contoh.go.id"), stop)

        # Tidak ada yang perlu diawasi pada alamat yang tidak dapat berubah;
        # mengawasinya berarti satu thread yang bangun tiap menit tanpa alasan.
        self.assertTrue(self.recorder.calls)

    def test_a_channel_without_credentials_is_left_alone(self) -> None:
        result = webhooks.publish(
            settings(telegram_token=None, tunnel_metrics="http://cloudflared:20241"),
        )

        # Saluran yang belum dikonfigurasi harus diam, bukan mendaftarkan
        # alamat atas nama token yang tidak ada.
        self.assertNotIn("telegram", result)
        self.assertIn("whatsapp", result)


if __name__ == "__main__":
    unittest.main()
