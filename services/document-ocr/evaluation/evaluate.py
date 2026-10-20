from __future__ import annotations

import argparse
import json
import time
from pathlib import Path

from app.extractor import DocumentExtractor
from evaluation.metrics import EvaluationMetrics


def main() -> None:
    parser = argparse.ArgumentParser(description="Avalia OCR sem imprimir dados pessoais das amostras.")
    parser.add_argument("manifest", type=Path, help="Manifesto JSONL privado com os resultados esperados.")
    parser.add_argument("--samples", type=Path, default=Path("evaluation/samples"))
    parser.add_argument("--output", type=Path, default=Path("evaluation/reports/latest.json"))
    args = parser.parse_args()

    extractor = DocumentExtractor()
    metrics = EvaluationMetrics()

    for line in args.manifest.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue

        sample = json.loads(line)
        try:
            images = [("front", (args.samples / sample["front"]).read_bytes())]
            if sample.get("back"):
                images.append(("back", (args.samples / sample["back"]).read_bytes()))

            started = time.perf_counter()
            result = extractor.extract(sample["document_type"], images, include_portrait=False)
            metrics.add(sample["expected"], result, time.perf_counter() - started)
        except Exception:
            metrics.failure()

    summary = metrics.summary()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(summary, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(summary, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
