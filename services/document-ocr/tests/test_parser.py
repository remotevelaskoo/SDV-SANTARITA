import unittest

from app.extractor import OcrLine, parse_fields


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


if __name__ == "__main__":
    unittest.main()
