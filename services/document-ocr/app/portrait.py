from __future__ import annotations

import base64
import io
from dataclasses import dataclass
from typing import Iterable

import cv2
import numpy as np
from PIL import Image, ImageOps


@dataclass(frozen=True)
class PortraitCandidate:
    source: str
    image: Image.Image
    area: int


class PortraitExtractor:
    def __init__(self) -> None:
        cascade_path = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
        self._detector = cv2.CascadeClassifier(cascade_path)
        if self._detector.empty():
            raise RuntimeError("portrait_detector_unavailable")

    def extract(self, images: Iterable[tuple[str, bytes]], include_image: bool = False) -> dict:
        candidates: list[PortraitCandidate] = []

        for source, content in images:
            candidate = self._candidate(source, content)
            if candidate is not None:
                candidates.append(candidate)

        if not candidates:
            return {"detected": False, "confidence": None, "source": None, "image_base64": None}

        selected = max(candidates, key=lambda candidate: candidate.area)
        encoded = None
        if include_image:
            selected.image.thumbnail((640, 640), Image.Resampling.LANCZOS)
            output = io.BytesIO()
            selected.image.save(output, format="JPEG", quality=90, optimize=True)
            encoded = base64.b64encode(output.getvalue()).decode("ascii")

        return {
            "detected": True,
            "confidence": None,
            "source": selected.source,
            "image_base64": encoded,
        }

    def _candidate(self, source: str, content: bytes) -> PortraitCandidate | None:
        with Image.open(io.BytesIO(content)) as opened:
            image = ImageOps.exif_transpose(opened).convert("RGB")

        pixels = np.asarray(image)
        gray = cv2.cvtColor(pixels, cv2.COLOR_RGB2GRAY)
        minimum = max(40, min(image.size) // 12)
        faces = self._detector.detectMultiScale(
            gray,
            scaleFactor=1.08,
            minNeighbors=5,
            minSize=(minimum, minimum),
        )

        if len(faces) == 0:
            return None

        x, y, width, height = max(faces, key=lambda face: int(face[2]) * int(face[3]))
        margin_x = int(width * 0.35)
        margin_top = int(height * 0.45)
        margin_bottom = int(height * 0.65)
        left = max(0, int(x) - margin_x)
        top = max(0, int(y) - margin_top)
        right = min(image.width, int(x + width) + margin_x)
        bottom = min(image.height, int(y + height) + margin_bottom)
        crop = image.crop((left, top, right, bottom))

        return PortraitCandidate(source=source, image=crop, area=int(width) * int(height))
