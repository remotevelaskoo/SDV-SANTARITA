"""Leitura real com o RapidOCR sobre um documento SINTÉTICO desenhado em memória.

Roda só com OCR_ENGINE_TESTS=1 (carrega os modelos e leva alguns segundos).
Não usa nenhum documento real e não grava imagem em disco.
"""

from __future__ import annotations

import io
import os
import unittest

from PIL import Image, ImageDraw, ImageFont


def synthetic_cnh() -> Image.Image:
    font = ImageFont.load_default(size=34)
    image = Image.new("RGB", (1500, 1000), (80, 80, 80))
    draw = ImageDraw.Draw(image)
    draw.rectangle((100, 100, 1400, 900), fill=(235, 235, 228))
    y = 150
    for text in ["CARTEIRA NACIONAL DE HABILITACAO", "NOME", "PESSOA FICTICIA DE TESTE", "CPF 123.456.789-09", "DATA NASCIMENTO 15/05/1992", "REGISTRO 01234567890"]:
        draw.text((160, y), text, fill=(20, 20, 20), font=font)
        y += 110
    return image


def encode(image: Image.Image) -> bytes:
    output = io.BytesIO()
    image.save(output, format="JPEG", quality=92)
    return output.getvalue()


@unittest.skipUnless(os.getenv("OCR_ENGINE_TESTS") == "1", "defina OCR_ENGINE_TESTS=1 para usar o motor real")
class SyntheticEngineTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        from app.extractor import DocumentExtractor

        cls.extractor = DocumentExtractor()

    def assert_reads_all_fields(self, image: Image.Image) -> dict:
        result = self.extractor.extract("cnh", [("front", encode(image))])
        values = {name: field["value"] for name, field in result["fields"].items()}

        self.assertEqual(
            {"name": "Pessoa Ficticia de Teste", "cpf": "123.456.789-09", "birth_date": "1992-05-15", "document_number": "01234567890"},
            values,
        )
        self.assertEqual("cnh", result["diagnostics"]["detected_type"])
        return result

    def test_reads_an_upright_synthetic_document(self) -> None:
        result = self.assert_reads_all_fields(synthetic_cnh())
        self.assertEqual("ok", result["diagnostics"]["stage"])

    def test_reads_a_sideways_synthetic_document_after_rotation(self) -> None:
        result = self.assert_reads_all_fields(synthetic_cnh().rotate(90, expand=True))
        self.assertEqual({"front": 90}, result["diagnostics"]["rotation_applied"])


if __name__ == "__main__":
    unittest.main()
