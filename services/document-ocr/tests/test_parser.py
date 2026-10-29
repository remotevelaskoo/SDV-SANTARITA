import unittest
from threading import Lock
from types import SimpleNamespace


from app.extractor import DocumentExtractor, OcrLine, detect_document_type, diagnose, parse_fields
from tests.test_quality import encode, synthetic_card


class FakeOcrEngine:
    def __call__(self, _content: bytes) -> SimpleNamespace:
        return SimpleNamespace(txts=["NOME", "MARIA TESTE DA SILVA"], scores=[0.99, 0.98])


class FailingPortraitExtractor:
    def extract(self, *_args, **_kwargs) -> dict:
        raise RuntimeError("detector failed")


class ParserTest(unittest.TestCase):
    def test_extracts_cnh_fields_from_recognized_lines(self) -> None:
        lines = [
            OcrLine("CARTEIRA NACIONAL DE HABILITACAO", 0.99, "front"),
            OcrLine("NOME", 0.99, "front"),
            OcrLine("MARIA TESTE DA SILVA", 0.98, "front"),
            OcrLine("CPF 123.456.789-09", 0.97, "front"),
            OcrLine("DATA NASCIMENTO 15/05/1992", 0.96, "front"),
            OcrLine("REGISTRO 01234567890", 0.95, "front"),
        ]

        fields = parse_fields("cnh", lines)

        self.assertEqual("Maria Teste da Silva", fields["name"]["value"])
        self.assertEqual("123.456.789-09", fields["cpf"]["value"])
        self.assertEqual("1992-05-15", fields["birth_date"]["value"])
        self.assertEqual("01234567890", fields["document_number"]["value"])
        self.assertEqual("front", fields["name"]["source"])

    def test_rejects_an_invalid_cpf_instead_of_suggesting_it(self) -> None:
        fields = parse_fields("rg_cin", [OcrLine("CPF 111.111.111-11", 0.99, "front")])

        self.assertIsNone(fields["cpf"]["value"])

    def test_portrait_failure_does_not_block_text_extraction(self) -> None:
        extractor = DocumentExtractor.__new__(DocumentExtractor)
        extractor._engine = FakeOcrEngine()
        extractor._portrait_extractor = FailingPortraitExtractor()
        extractor._lock = Lock()

        result = extractor.extract("cnh", [("front", b"image")])

        self.assertEqual("Maria Teste da Silva", result["fields"]["name"]["value"])
        self.assertFalse(result["portrait"]["detected"])
        self.assertEqual("unavailable", result["portrait"]["status"])

    def test_uses_cnh_layout_to_link_name_and_birth_date_to_their_labels(self) -> None:
        lines = [
            OcrLine("1 NOME E SOBRENOME", 0.99, "front", (300, 300, 570, 330)),
            OcrLine("4a VALIDADE", 0.98, "front", (1300, 300, 1470, 330)),
            OcrLine("PESSOA FICTICIA DE TESTE", 0.97, "front", (300, 345, 830, 380)),
            OcrLine("20/08/2030", 0.99, "front", (1310, 345, 1470, 380)),
            OcrLine("3 DATA, LOCAL E UF DE NASCIMENTO", 0.98, "front", (750, 405, 1100, 435)),
            OcrLine("15/05/1992 SAO PAULO SP", 0.97, "front", (750, 445, 1100, 480)),
            OcrLine("4a DATA DE EMISSAO", 0.99, "front", (750, 500, 930, 530)),
            OcrLine("4b DATA DE VALIDADE", 0.99, "front", (1040, 500, 1180, 530)),
            OcrLine("10/10/2020", 0.99, "front", (750, 535, 920, 570)),
            OcrLine("10/10/2030", 0.99, "front", (1040, 535, 1210, 570)),
        ]

        fields = parse_fields("cnh", lines)

        self.assertEqual("Pessoa Ficticia de Teste", fields["name"]["value"])
        self.assertEqual("1992-05-15", fields["birth_date"]["value"])


class RotationAwareEngine:
    """Só "lê" quando recebe a imagem em pé (mais alta que larga)."""

    def __init__(self) -> None:
        self.calls: list[tuple[int, int]] = []

    def __call__(self, image) -> SimpleNamespace:
        height, width = image.shape[:2]
        self.calls.append((height, width))
        if height > width:
            return SimpleNamespace(
                txts=["NOME", "MARIA TESTE DA SILVA", "CPF 123.456.789-09", "DATA NASCIMENTO 15/05/1992", "REGISTRO 01234567890"],
                scores=[0.99, 0.97, 0.96, 0.95, 0.94],
            )
        return SimpleNamespace(txts=["#"], scores=[0.2])


class SilentPortraitExtractor:
    def extract(self, *_args, **_kwargs) -> dict:
        return {"detected": False, "confidence": None, "source": None, "image_base64": None}


def build_extractor(engine) -> DocumentExtractor:
    extractor = DocumentExtractor.__new__(DocumentExtractor)
    extractor._engine = engine
    extractor._portrait_extractor = SilentPortraitExtractor()
    extractor._lock = Lock()
    return extractor


class DiagnosticsTest(unittest.TestCase):
    def test_rg_label_does_not_match_inside_orgao_expedidor(self) -> None:
        lines = [
            OcrLine("ORGAO EXPEDIDOR SSP 20190312", 0.99, "back"),
            OcrLine("REGISTRO GERAL 12.345.678-9", 0.97, "back"),
        ]

        fields = parse_fields("rg_cin", lines)

        self.assertEqual("12.345.678-9", fields["document_number"]["value"])

    def test_cnh_registry_number_is_not_taken_as_cpf(self) -> None:
        lines = [
            OcrLine("N REGISTRO 01234567890", 0.99, "front"),
            OcrLine("CPF", 0.98, "front"),
            OcrLine("123.456.789-09", 0.97, "front"),
        ]

        self.assertEqual("123.456.789-09", parse_fields("cnh", lines)["cpf"]["value"])
        self.assertIsNone(parse_fields("cnh", lines[:1])["cpf"]["value"])

    def test_fields_report_whether_they_passed_validation(self) -> None:
        fields = parse_fields("cnh", [OcrLine("CPF 123.456.789-09", 0.97, "front"), OcrLine("NASCIMENTO 15/05/1992", 0.9, "front")])

        self.assertTrue(fields["cpf"]["validated"])
        self.assertTrue(fields["birth_date"]["validated"])
        self.assertFalse(fields["name"]["validated"])

    def test_detects_document_type_by_keywords(self) -> None:
        self.assertEqual("cnh", detect_document_type([OcrLine("CARTEIRA NACIONAL DE HABILITACAO", 0.9, "front")]))
        self.assertEqual("rg_cin", detect_document_type([OcrLine("REPUBLICA FEDERATIVA DO BRASIL", 0.9, "front"), OcrLine("CARTEIRA DE IDENTIDADE", 0.9, "front")]))
        self.assertIsNone(detect_document_type([OcrLine("TEXTO QUALQUER", 0.9, "front")]))

    def test_no_text_on_a_bad_capture_points_to_quality(self) -> None:
        result = diagnose("cnh", ["front"], [{"source": "front", "issues": ["desfocada"]}], [], parse_fields("cnh", []), None, {})

        self.assertEqual("quality", result["stage"])
        self.assertEqual(["sem_texto"], result["issues"])
        self.assertEqual(["desfocada"], result["quality_issues"])
        self.assertTrue(result["recapture_recommended"])

    def test_no_text_on_a_good_capture_points_to_ocr(self) -> None:
        result = diagnose("cnh", ["front"], [{"source": "front", "issues": []}], [], parse_fields("cnh", []), None, {})

        self.assertEqual("ocr", result["stage"])

    def test_text_without_fields_on_rg_front_suggests_the_back_side(self) -> None:
        lines = [OcrLine("REPUBLICA FEDERATIVA DO BRASIL", 0.95, "front"), OcrLine("ASSINATURA DO TITULAR", 0.93, "front")]

        result = diagnose("rg_cin", ["front"], [{"source": "front", "issues": []}], lines, parse_fields("rg_cin", lines), None, {})

        self.assertEqual("interpretation", result["stage"])
        self.assertIn("campos_nao_localizados", result["issues"])
        self.assertIn("verso_recomendado", result["issues"])

    def test_wrong_document_type_and_invalid_cpf_are_reported(self) -> None:
        lines = [OcrLine("CARTEIRA NACIONAL DE HABILITACAO", 0.95, "front"), OcrLine("CPF 111.222.333-44", 0.9, "front")]

        result = diagnose("rg_cin", ["front", "back"], [], lines, parse_fields("rg_cin", lines), detect_document_type(lines), {})

        self.assertIn("tipo_divergente", result["issues"])
        self.assertIn("cpf_digito_invalido", result["issues"])
        self.assertEqual("cnh", result["detected_type"])
        self.assertTrue(result["recapture_recommended"])

    def test_low_field_confidence_is_flagged_for_human_review(self) -> None:
        lines = [OcrLine("NOME", 0.99, "front"), OcrLine("MARIA TESTE DA SILVA", 0.42, "front")]

        result = diagnose("cnh", ["front"], [], lines, parse_fields("cnh", lines), None, {})

        self.assertIn("confianca_baixa", result["issues"])

    def test_diagnostics_never_carry_text_or_values(self) -> None:
        extractor = build_extractor(RotationAwareEngine())

        result = extractor.extract("cnh", [("front", encode(synthetic_card(size=(900, 1400))))])

        serialized = repr(result["diagnostics"])
        self.assertNotIn("MARIA", serialized.upper())
        self.assertNotIn("123.456", serialized)

    def test_a_sideways_photo_is_read_after_rotation(self) -> None:
        engine = RotationAwareEngine()
        extractor = build_extractor(engine)

        result = extractor.extract("cnh", [("front", encode(synthetic_card(size=(1400, 900))))])

        self.assertEqual("Maria Teste da Silva", result["fields"]["name"]["value"])
        self.assertEqual({"front": 90}, result["diagnostics"]["rotation_applied"])
        self.assertEqual("ok", result["diagnostics"]["stage"])
        self.assertGreater(len(engine.calls), 1)

    def test_an_upright_photo_is_read_only_once(self) -> None:
        engine = RotationAwareEngine()
        extractor = build_extractor(engine)

        extractor.extract("cnh", [("front", encode(synthetic_card(size=(900, 1400))))])

        self.assertEqual(1, len(engine.calls))


if __name__ == "__main__":
    unittest.main()
