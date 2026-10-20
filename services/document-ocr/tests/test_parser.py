import unittest
from threading import Lock
from types import SimpleNamespace

from app.extractor import DocumentExtractor, OcrLine, parse_fields


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


if __name__ == "__main__":
    unittest.main()
