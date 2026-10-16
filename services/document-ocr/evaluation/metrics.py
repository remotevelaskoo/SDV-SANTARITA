from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass, field


FIELDS = ("name", "cpf", "birth_date", "document_number")


def normalized(field_name: str, value: str | None) -> str:
    if not value:
        return ""

    text = unicodedata.normalize("NFKD", value)
    text = "".join(character for character in text if not unicodedata.combining(character))
    if field_name in {"cpf", "document_number"}:
        return re.sub(r"\W", "", text).upper()

    return re.sub(r"\s+", " ", text).strip().upper()


@dataclass
class EvaluationMetrics:
    sample_count: int = 0
    elapsed_seconds: list[float] = field(default_factory=list)
    fields_expected: dict[str, int] = field(default_factory=lambda: {name: 0 for name in FIELDS})
    fields_correct: dict[str, int] = field(default_factory=lambda: {name: 0 for name in FIELDS})
    portrait_expected: int = 0
    portrait_correct: int = 0
    technical_failures: int = 0

    def add(self, expected: dict, result: dict, elapsed_seconds: float) -> None:
        self.sample_count += 1
        self.elapsed_seconds.append(elapsed_seconds)

        result_fields = result.get("fields", {})
        for field_name in FIELDS:
            expected_value = expected.get("fields", {}).get(field_name)
            if expected_value in (None, ""):
                continue

            self.fields_expected[field_name] += 1
            extracted = result_fields.get(field_name, {}).get("value")
            if normalized(field_name, extracted) == normalized(field_name, expected_value):
                self.fields_correct[field_name] += 1

        if "portrait" in expected:
            self.portrait_expected += 1
            if bool(result.get("portrait", {}).get("detected")) is bool(expected["portrait"]):
                self.portrait_correct += 1

    def failure(self) -> None:
        self.sample_count += 1
        self.technical_failures += 1

    def summary(self) -> dict:
        field_accuracy = {
            name: {
                "correct": self.fields_correct[name],
                "expected": self.fields_expected[name],
                "accuracy": round(self.fields_correct[name] / self.fields_expected[name], 4)
                if self.fields_expected[name]
                else None,
            }
            for name in FIELDS
        }
        elapsed = sorted(self.elapsed_seconds)

        return {
            "samples": self.sample_count,
            "technical_failures": self.technical_failures,
            "field_accuracy": field_accuracy,
            "portrait_detection_accuracy": round(self.portrait_correct / self.portrait_expected, 4)
            if self.portrait_expected
            else None,
            "latency_seconds": {
                "average": round(sum(elapsed) / len(elapsed), 4) if elapsed else None,
                "maximum": round(max(elapsed), 4) if elapsed else None,
            },
        }
