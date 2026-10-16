from __future__ import annotations

import base64
import io
import unittest

import numpy as np
from PIL import Image

from app.portrait import PortraitExtractor


class FakeDetector:
    def detectMultiScale(self, *_args, **_kwargs) -> np.ndarray:
        return np.asarray([[80, 40, 60, 70], [10, 10, 20, 20]])


def document_image() -> bytes:
    output = io.BytesIO()
    Image.new("RGB", (300, 180), "white").save(output, format="PNG")
    return output.getvalue()


class PortraitExtractorTest(unittest.TestCase):
    def setUp(self) -> None:
        self.extractor = PortraitExtractor.__new__(PortraitExtractor)
        self.extractor._detector = FakeDetector()

    def test_selects_the_largest_face_and_returns_a_private_jpeg_crop(self) -> None:
        result = self.extractor.extract([("front", document_image())], include_image=True)

        self.assertTrue(result["detected"])
        self.assertEqual("front", result["source"])
        self.assertIsNone(result["confidence"])
        decoded = base64.b64decode(result["image_base64"], validate=True)

        with Image.open(io.BytesIO(decoded)) as crop:
            self.assertEqual("JPEG", crop.format)
            self.assertGreater(crop.width, 60)
            self.assertGreater(crop.height, 70)

    def test_omits_the_sensitive_crop_when_it_was_not_requested(self) -> None:
        result = self.extractor.extract([("front", document_image())], include_image=False)

        self.assertTrue(result["detected"])
        self.assertIsNone(result["image_base64"])


if __name__ == "__main__":
    unittest.main()
