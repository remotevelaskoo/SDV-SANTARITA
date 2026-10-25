"""Testes com imagens sintéticas geradas em memória: nenhum documento real."""

from __future__ import annotations

import io
import unittest

from PIL import Image, ImageDraw, ImageFilter

from app.quality import prepare


def synthetic_card(
    size: tuple[int, int] = (1400, 900),
    background: int = 90,
    card: int = 232,
    margin: int = 120,
    blur: float = 0,
    glare: bool = False,
) -> Image.Image:
    """Cartão claro com blocos escuros imitando linhas de texto, sobre fundo neutro."""
    width, height = size
    image = Image.new("RGB", size, (background, background, background))
    draw = ImageDraw.Draw(image)
    draw.rectangle((margin, margin, width - margin, height - margin), fill=(card, card, card))

    y = margin + 40
    while y < height - margin - 40:
        x = margin + 40
        while x < width - margin - 120:
            draw.rectangle((x, y, x + 70, y + 18), fill=(25, 25, 25))
            x += 100
        y += 55

    if glare:
        draw.ellipse((width // 3, height // 4, width // 3 + 420, height // 4 + 360), fill=(255, 255, 255))
    if blur:
        image = image.filter(ImageFilter.GaussianBlur(blur))

    return image


def encode(image: Image.Image, fmt: str = "JPEG", **kwargs) -> bytes:
    output = io.BytesIO()
    image.save(output, format=fmt, quality=92, **kwargs)
    return output.getvalue()


class QualityTest(unittest.TestCase):
    def test_a_well_framed_sharp_capture_has_no_issues(self) -> None:
        prepared = prepare("front", encode(synthetic_card()))

        self.assertEqual([], prepared.quality["issues"], prepared.quality["metrics"])
        self.assertTrue(prepared.quality["ok"])
        self.assertIsNotNone(prepared.array)

    def test_detects_low_resolution(self) -> None:
        prepared = prepare("front", encode(synthetic_card(size=(500, 320), margin=40)))

        self.assertIn("resolucao_baixa", prepared.quality["issues"])

    def test_detects_dark_and_overexposed_images(self) -> None:
        dark = synthetic_card(background=10, card=45)
        bright = synthetic_card(background=250, card=250)

        self.assertIn("escura", prepare("front", encode(dark)).quality["issues"])
        self.assertIn("clara_demais", prepare("front", encode(bright)).quality["issues"])

    def test_detects_blur(self) -> None:
        prepared = prepare("front", encode(synthetic_card(blur=9)))

        self.assertIn("desfocada", prepared.quality["issues"])

    def test_detects_glare(self) -> None:
        prepared = prepare("front", encode(synthetic_card(glare=True)))

        self.assertIn("reflexo", prepared.quality["issues"])

    def test_detects_a_document_cut_by_the_frame(self) -> None:
        prepared = prepare("front", encode(synthetic_card(margin=-60)))

        self.assertIn("possivelmente_cortado", prepared.quality["issues"])

    def test_applies_exif_orientation_before_reading(self) -> None:
        image = synthetic_card(size=(1400, 900))
        exif = Image.Exif()
        exif[274] = 6  # foto do celular girada 90°

        prepared = prepare("front", encode(image, exif=exif))

        self.assertTrue(prepared.quality["exif_rotated"])
        self.assertEqual((1400, 900), prepared.array.shape[:2])

    def test_invalid_content_is_reported_without_raising(self) -> None:
        prepared = prepare("back", b"not an image")

        self.assertIsNone(prepared.array)
        self.assertEqual(["imagem_invalida"], prepared.quality["issues"])
        self.assertEqual("back", prepared.quality["source"])

    def test_report_contains_only_measurements(self) -> None:
        prepared = prepare("front", encode(synthetic_card()))

        self.assertEqual(
            {"width", "height", "brightness", "contrast", "glare_ratio", "sharpness", "margin_edge_ratio"},
            set(prepared.quality["metrics"]),
        )


if __name__ == "__main__":
    unittest.main()
