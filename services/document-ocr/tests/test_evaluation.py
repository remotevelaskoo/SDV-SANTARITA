from __future__ import annotations

import unittest

from evaluation.metrics import EvaluationMetrics, normalized


class EvaluationMetricsTest(unittest.TestCase):
    def test_normalizes_formatting_without_exposing_values_in_summary(self) -> None:
        self.assertEqual("12345678909", normalized("cpf", "123.456.789-09"))
        self.assertEqual("MARIA DA SILVA", normalized("name", "  Maria   da Silva "))

        metrics = EvaluationMetrics()
        metrics.add(
            {"fields": {"name": "Maria da Silva", "cpf": "123.456.789-09"}, "portrait": True},
            {
                "fields": {
                    "name": {"value": "MARIA DA SILVA"},
                    "cpf": {"value": "12345678909"},
                },
                "portrait": {"detected": True},
            },
            0.5,
        )

        summary = metrics.summary()
        self.assertEqual(1.0, summary["field_accuracy"]["name"]["accuracy"])
        self.assertEqual(1.0, summary["portrait_detection_accuracy"])
        self.assertNotIn("Maria", str(summary))

    def test_counts_technical_failures_without_personal_data(self) -> None:
        metrics = EvaluationMetrics()
        metrics.failure()

        self.assertEqual(1, metrics.summary()["technical_failures"])


if __name__ == "__main__":
    unittest.main()
