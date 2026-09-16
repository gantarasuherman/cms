"""Tests untuk urutan naiknya layanan.

Bot hampir selalu siap lebih dulu daripada nginx. Sekali kalah cepat, bot dulu
berjalan SELAMANYA tanpa kredensial: tidak dapat memverifikasi tanda tangan,
tidak dapat mendaftarkan webhook, tidak dapat membalas — tanpa satu pun tanda
bahwa ada yang rusak, sebab prosesnya sendiri sehat dan lognya diam. Satu-
satunya gejalanya adalah "botnya tidak jalan", yang bisa berarti apa saja.

    cd bot && python3 -m unittest discover tests
"""
from __future__ import annotations

import os
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

os.environ["BOT_INTERNAL_TOKEN"] = os.environ.get("BOT_INTERNAL_TOKEN") or "uji"

from app import __main__ as entry  # noqa: E402
from app.http import HttpError  # noqa: E402
from app.laravel import Laravel  # noqa: E402


class Panel:
    """Laravel tiruan yang menjawab menurut daftar yang diberikan."""

    def __init__(self, *answers) -> None:
        self.answers = list(answers)
        self.asked = 0

    def config(self):
        answer = self.answers[min(self.asked, len(self.answers) - 1)]
        self.asked += 1

        return answer


class WaitingForThePanel(unittest.TestCase):
    def test_it_keeps_asking_until_the_panel_answers(self) -> None:
        panel = Panel(None, None, {"channels": {"telegram": {"token": "123:uji"}}})

        remote = entry._await_config(panel, attempts=5, pause=0)

        self.assertEqual({"telegram": {"token": "123:uji"}}, remote["channels"])
        self.assertEqual(3, panel.asked)

    def test_an_empty_answer_is_an_answer(self) -> None:
        panel = Panel({"channels": {}})

        self.assertEqual({"channels": {}}, entry._await_config(panel, attempts=5, pause=0))
        # Pemasangan baru yang belum dikonfigurasi harus tetap naik seketika,
        # bukan tertahan pada setiap kali start menunggu sesuatu yang memang
        # tidak akan pernah datang.
        self.assertEqual(1, panel.asked)

    def test_it_waits_without_a_deadline(self) -> None:
        # Batas waktu pernah dipasang di sini, enam puluh detik, dan akibatnya
        # persis cacat yang hendak diperbaikinya: satu kali nginx lambat naik,
        # bot menyerah lalu berjalan berjam-jam tanpa kredensial — tidak dapat
        # memverifikasi tanda tangan, mendaftarkan alamat, maupun menguras
        # outbox. Berjalan begitu hanya berarti berpura-pura.
        self.assertEqual(0, entry.CONFIG_ATTEMPTS, 'Penantian panel harus tanpa batas.')

        panel = Panel(*([None] * 40), {"channels": {"telegram": {"token": "123:uji"}}})

        remote = entry._await_config(panel, pause=0)

        # Lewat dari batas lama yang 30 percobaan, dan tetap sampai.
        self.assertEqual(41, panel.asked)
        self.assertIn("telegram", remote["channels"])

    def test_a_bounded_wait_still_gives_up_for_the_tests_that_ask_for_one(self) -> None:
        panel = Panel(None)

        self.assertIsNone(entry._await_config(panel, attempts=3, pause=0))
        self.assertEqual(3, panel.asked)


class UnreachableIsNotUnconfigured(unittest.TestCase):
    """Dua keadaan yang dulu tidak dapat dibedakan, dan itulah cacatnya."""

    def setUp(self) -> None:
        self.laravel = Laravel.__new__(Laravel)
        self.laravel.base = 'http://nginx/api/bot'
        self.laravel.headers = {}

    def test_a_dead_nginx_reads_as_unknown(self) -> None:
        import app.laravel as module

        def refuse(*_a, **_kw):
            raise HttpError(502, "Bad Gateway")

        original, module.request_json = module.request_json, refuse

        try:
            # None, bukan {}. Nginx yang memegang alamat lama php-fpm menjawab
            # 502 pada segalanya, dan itu keadaan sementara — bukan pernyataan
            # bahwa tidak ada saluran yang dikonfigurasi.
            self.assertIsNone(self.laravel.config())
        finally:
            module.request_json = original

    def test_a_refused_connection_reads_as_unknown(self) -> None:
        import app.laravel as module

        def refuse(*_a, **_kw):
            raise OSError("Connection refused")

        original, module.request_json = module.request_json, refuse

        try:
            # Nginx yang belum sempat mendengarkan sama sekali: sama-sama
            # sementara, dan dulu dilaporkan sebagai "tidak ada kredensial".
            self.assertIsNone(self.laravel.config())
        finally:
            module.request_json = original

    def test_an_answering_panel_with_nothing_set_up_reads_as_empty(self) -> None:
        import app.laravel as module

        original, module.request_json = module.request_json, lambda *_a, **_kw: {"channels": {}}

        try:
            self.assertEqual({"channels": {}}, self.laravel.config())
        finally:
            module.request_json = original


if __name__ == "__main__":
    unittest.main()
