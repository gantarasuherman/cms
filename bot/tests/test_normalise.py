"""Tests for the parts that turn a platform's shape into ours.

Pure functions with no network: this is where a platform quietly changing a
field breaks the bot, and where a test is worth the most.

    cd bot && python3 -m unittest discover tests
"""
from __future__ import annotations

import os
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

os.environ.setdefault("BOT_INTERNAL_TOKEN", "uji")
os.environ.setdefault("TELEGRAM_BOT_TOKEN", "123:uji")

from app.config import Settings  # noqa: E402
from app.transports.telegram import Telegram  # noqa: E402
from app.transports.whatsapp import WhatsApp  # noqa: E402


def settings() -> Settings:
    return Settings.load()


class TelegramNormalise(unittest.TestCase):
    def setUp(self) -> None:
        self.telegram = Telegram(settings())

    def update(self, message: dict) -> dict:
        return {"update_id": 1, "message": {"message_id": 7, "chat": {"id": -1001, "type": "private"},
                                            "from": {"id": 5, "first_name": "Warga", "username": "warga"}, **message}}

    def test_a_text_message_carries_the_chat_as_the_sender(self) -> None:
        payload = self.telegram.normalise(self.update({"text": "halo"}))

        self.assertEqual("telegram", payload["channel"])
        self.assertEqual("halo", payload["text"])
        self.assertEqual("-1001", payload["from"])
        self.assertEqual("Warga", payload["sender_name"])

    def test_the_external_id_includes_the_chat(self) -> None:
        # Message ids restart per chat, so without the chat two people would
        # collide and the second be discarded as a replay.
        first = self.telegram.normalise(self.update({"text": "a"}))
        other = {"update_id": 2, "message": {"message_id": 7, "chat": {"id": -2002}, "from": {}, "text": "b"}}
        second = self.telegram.normalise(other)

        self.assertNotEqual(first["external_id"], second["external_id"])
        self.assertIn("-1001", first["external_id"])

    def test_a_location_becomes_coordinates(self) -> None:
        payload = self.telegram.normalise(self.update({"location": {"latitude": -6.2, "longitude": 106.8}}))

        self.assertEqual("location", payload["type"])
        self.assertAlmostEqual(-6.2, payload["latitude"])
        self.assertAlmostEqual(106.8, payload["longitude"])

    def test_kinds_the_flow_will_reject_still_arrive_named(self) -> None:
        # A sticker is a thing a person did; answering 422 would make Telegram
        # retry it forever instead of letting the flow say "not what I asked".
        for field, expected in [("sticker", "sticker"), ("contact", "contact"), ("video", "video")]:
            payload = self.telegram.normalise(self.update({field: {"x": 1}}))
            self.assertEqual(expected, payload["type"], field)

    def test_a_caption_is_read_as_the_text(self) -> None:
        payload = self.telegram.normalise(self.update({"caption": "ini fotonya", "sticker": {}}))

        self.assertEqual("ini fotonya", payload["text"])

    def test_an_update_without_a_message_is_skipped(self) -> None:
        self.assertIsNone(self.telegram.normalise({"update_id": 9}))

    def test_an_edited_message_is_still_handled(self) -> None:
        payload = self.telegram.normalise(
            {"update_id": 3, "edited_message": {"message_id": 8, "chat": {"id": 1}, "from": {}, "text": "ralat"}}
        )

        self.assertEqual("ralat", payload["text"])


class WhatsAppNormalise(unittest.TestCase):
    def setUp(self) -> None:
        self.whatsapp = WhatsApp(settings())

    def entry(self, message: dict) -> dict:
        return {"changes": [{"value": {
            "contacts": [{"wa_id": "628120000001", "profile": {"name": "Warga"}}],
            "messages": [{"id": "wamid.X", "from": "628120000001", **message}],
        }}]}

    def test_a_text_message(self) -> None:
        (payload,) = self.whatsapp.normalise(self.entry({"type": "text", "text": {"body": "halo"}}))

        self.assertEqual("whatsapp", payload["channel"])
        self.assertEqual("halo", payload["text"])
        self.assertEqual("628120000001", payload["from"])
        self.assertEqual("Warga", payload["sender_name"])
        self.assertEqual("wa:wamid.X", payload["external_id"])

    def test_a_location(self) -> None:
        (payload,) = self.whatsapp.normalise(
            self.entry({"type": "location", "location": {"latitude": -6.9, "longitude": 107.6}})
        )

        self.assertEqual("location", payload["type"])
        self.assertAlmostEqual(-6.9, payload["latitude"])

    def test_a_tapped_button_answers_as_its_value(self) -> None:
        # So a button and typing its number mean exactly the same thing to the
        # flow, and no node needs to know which the person used.
        (payload,) = self.whatsapp.normalise(self.entry(
            {"type": "interactive", "interactive": {"button_reply": {"id": "2", "title": "Cek Aduan"}}}
        ))

        self.assertEqual("2", payload["text"])

    def test_several_messages_in_one_webhook_are_all_returned(self) -> None:
        entry = {"changes": [{"value": {"contacts": [], "messages": [
            {"id": "a", "from": "1", "type": "text", "text": {"body": "satu"}},
            {"id": "b", "from": "1", "type": "text", "text": {"body": "dua"}},
        ]}}]}

        self.assertEqual(["satu", "dua"], [m["text"] for m in self.whatsapp.normalise(entry)])

    def test_a_webhook_with_no_messages_yields_nothing(self) -> None:
        # Delivery receipts arrive on the same hook and are not conversation.
        self.assertEqual([], self.whatsapp.normalise({"changes": [{"value": {"statuses": [{"id": "x"}]}}]}))


class WhatsAppSignature(unittest.TestCase):
    def test_an_unsigned_body_is_refused(self) -> None:
        os.environ["WHATSAPP_APP_SECRET"] = "rahasia"
        whatsapp = WhatsApp(Settings.load())

        self.assertFalse(whatsapp.verify_signature(b"{}", None))
        self.assertFalse(whatsapp.verify_signature(b"{}", "sha256=salah"))

    def test_a_correctly_signed_body_is_accepted(self) -> None:
        import hashlib
        import hmac

        os.environ["WHATSAPP_APP_SECRET"] = "rahasia"
        whatsapp = WhatsApp(Settings.load())
        body = b'{"entry":[]}'
        signature = "sha256=" + hmac.new(b"rahasia", body, hashlib.sha256).hexdigest()

        self.assertTrue(whatsapp.verify_signature(body, signature))

    def test_without_a_secret_everything_is_refused(self) -> None:
        # An unverified webhook is an open endpoint anyone can post complaints
        # to; failing shut is the only safe default.
        os.environ.pop("WHATSAPP_APP_SECRET", None)
        whatsapp = WhatsApp(Settings.load())

        self.assertFalse(whatsapp.verify_signature(b"{}", "sha256=apa pun"))


class Configuration(unittest.TestCase):
    def test_a_missing_internal_token_stops_the_service(self) -> None:
        saved = os.environ.pop("BOT_INTERNAL_TOKEN")
        try:
            with self.assertRaises(RuntimeError):
                Settings.load()
        finally:
            os.environ["BOT_INTERNAL_TOKEN"] = saved


if __name__ == "__main__":
    unittest.main()
