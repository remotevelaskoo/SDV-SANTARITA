from __future__ import annotations

import io
import os
import unittest
from unittest.mock import patch

from fastapi.testclient import TestClient
from PIL import Image

from app.main import app


class FakeExtractor:
    def extract(self, document_type: str, images: list[tuple[str, bytes]]) -> dict:
        return {
            "status": "completed",
            "engine": "test-engine",
            "model_version": "test-model",
            "document_type": document_type,
            "fields": {},
            "portrait": {"detected": False},
            "line_count": 0,
            "received_sources": [source for source, _ in images],
        }


def image_bytes() -> bytes:
    output = io.BytesIO()
    Image.new("RGB", (320, 200), "white").save(output, format="JPEG")
    return output.getvalue()


class ApiTest(unittest.TestCase):
    def setUp(self) -> None:
        self.extractor_patcher = patch("app.main.extractor", return_value=FakeExtractor())
        self.extractor_patcher.start()
        self.client = TestClient(app)

    def tearDown(self) -> None:
        self.extractor_patcher.stop()

    def test_extracts_front_and_back_with_internal_authentication(self) -> None:
        with patch.dict(os.environ, {"DOCUMENT_OCR_TOKEN": "secret"}):
            response = self.client.post(
                "/v1/extract",
                headers={"Authorization": "Bearer secret"},
                data={"document_type": "cnh"},
                files={
                    "front": ("front.jpg", image_bytes(), "image/jpeg"),
                    "back": ("back.jpg", image_bytes(), "image/jpeg"),
                },
            )

        self.assertEqual(200, response.status_code)
        self.assertEqual(["front", "back"], response.json()["received_sources"])

    def test_rejects_a_request_without_the_internal_token(self) -> None:
        with patch.dict(os.environ, {"DOCUMENT_OCR_TOKEN": "secret"}):
            response = self.client.post(
                "/v1/extract",
                data={"document_type": "cnh"},
                files={"front": ("front.jpg", image_bytes(), "image/jpeg")},
            )

        self.assertEqual(401, response.status_code)

    def test_rejects_content_that_is_not_an_image(self) -> None:
        response = self.client.post(
            "/v1/extract",
            data={"document_type": "rg_cin"},
            files={"front": ("front.jpg", b"not-an-image", "image/jpeg")},
        )

        self.assertEqual(422, response.status_code)
        self.assertEqual("invalid_image_content", response.json()["detail"])


if __name__ == "__main__":
    unittest.main()
