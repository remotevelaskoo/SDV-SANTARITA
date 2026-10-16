from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass
from datetime import datetime
from threading import Lock
from typing import Iterable

from rapidocr import RapidOCR

from .portrait import PortraitExtractor


ENGINE_NAME = "rapidocr"
MODEL_VERSION = "PP-OCRv6-small"


@dataclass(frozen=True)
class OcrLine:
    text: str
    confidence: float
    source: str
    box: tuple[float, float, float, float] | None = None

    @property
    def normalized(self) -> str:
        value = unicodedata.normalize("NFKD", self.text)
        value = "".join(char for char in value if not unicodedata.combining(char))
        return re.sub(r"\s+", " ", value.upper()).strip()


class DocumentExtractor:
    def __init__(self, engine: RapidOCR | None = None, portrait_extractor: PortraitExtractor | None = None) -> None:
        self._engine = engine or RapidOCR()
        self._portrait_extractor = portrait_extractor or PortraitExtractor()
        self._lock = Lock()

    def extract(self, document_type: str, images: Iterable[tuple[str, bytes]], include_portrait: bool = False) -> dict:
        image_list = list(images)
        lines: list[OcrLine] = []

        for source, content in image_list:
            with self._lock:
                result = self._engine(content)

            if not result or not result.txts:
                continue

            boxes = result.boxes if getattr(result, "boxes", None) is not None else ()
            scores = result.scores if getattr(result, "scores", None) is not None else ()
            for index, (text, score) in enumerate(zip(result.txts, scores, strict=False)):
                box = _bounding_box(boxes[index]) if index < len(boxes) else None
                lines.append(OcrLine(text=text, confidence=float(score), source=source, box=box))

        try:
            portrait = self._portrait_extractor.extract(image_list, include_image=include_portrait)
        except Exception:
            portrait = {
                "detected": False,
                "confidence": None,
                "source": None,
                "image_base64": None,
                "status": "unavailable",
            }

        return {
            "status": "completed",
            "engine": ENGINE_NAME,
            "model_version": MODEL_VERSION,
            "document_type": document_type,
            "fields": parse_fields(document_type, lines),
            "portrait": portrait,
            "line_count": len(lines),
        }


def parse_fields(document_type: str, lines: list[OcrLine]) -> dict:
    return {
        "name": _name_field(lines),
        "cpf": _cpf_field(lines),
        "birth_date": _birth_date_field(lines),
        "document_number": _document_number_field(document_type, lines),
    }


def _field(value: str | None, line: OcrLine | None) -> dict:
    return {
        "value": value,
        "confidence": round(line.confidence, 4) if value and line else None,
        "source": line.source if value and line else None,
    }


def _name_field(lines: list[OcrLine]) -> dict:
    labels = ("NOME COMPLETO", "NOME E SOBRENOME", "NOME", "NAME")

    for index, line in enumerate(lines):
        normalized = line.normalized

        for label in labels:
            if label in normalized and line.box:
                for candidate in _nearby_lines_below(line, lines):
                    if _looks_like_name(candidate.normalized) and not _is_document_label(candidate.normalized):
                        return _field(_title_name(candidate.text), candidate)

            if normalized == label and index + 1 < len(lines):
                candidate = lines[index + 1]
                if _looks_like_name(candidate.normalized) and not _is_document_label(candidate.normalized):
                    return _field(_title_name(candidate.text), candidate)

            if normalized.startswith(label + " "):
                value = line.text[len(label) :].strip(" :-")
                if _looks_like_name(value):
                    return _field(_title_name(value), line)

    return _field(None, None)


def _cpf_field(lines: list[OcrLine]) -> dict:
    pattern = re.compile(r"(?<!\d)(\d{3}[.\s]?\d{3}[.\s]?\d{3}[-\s]?\d{2})(?!\d)")

    for line in lines:
        match = pattern.search(line.normalized)
        if not match:
            continue

        digits = re.sub(r"\D", "", match.group(1))
        if _valid_cpf(digits):
            return _field(f"{digits[:3]}.{digits[3:6]}.{digits[6:9]}-{digits[9:]}", line)

    return _field(None, None)


def _birth_date_field(lines: list[OcrLine]) -> dict:
    pattern = re.compile(r"\b(\d{2})[/.\-](\d{2})[/.\-](\d{4})\b")
    labels = ("DATA DE NASCIMENTO", "DATA NASCIMENTO", "NASCIMENTO", "NASC", "DATE OF BIRTH")

    # Na CNH atual, o rótulo e o valor normalmente são blocos separados.
    # A posição visual evita confundir nascimento com validade, emissão ou
    # primeira habilitação, que também aparecem como datas no documento.
    for line in lines:
        if not line.box or not any(label in line.normalized for label in labels):
            continue

        match = pattern.search(line.normalized)
        if match and (value := _valid_birth_date(match)):
            return _field(value, line)

        for candidate in _nearby_lines_below(line, lines):
            match = pattern.search(candidate.normalized)
            if match and (value := _valid_birth_date(match)):
                return _field(value, candidate)

    prioritized = sorted(
        lines,
        key=lambda line: 0 if any(label in line.normalized for label in labels) else 1,
    )

    for line in prioritized:
        match = pattern.search(line.normalized)
        if not match:
            continue

        if value := _valid_birth_date(match):
            return _field(value, line)

    return _field(None, None)


def _document_number_field(document_type: str, lines: list[OcrLine]) -> dict:
    labels = (
        ("REGISTRO", "N REGISTRO", "NUMERO REGISTRO", "N DA CNH")
        if document_type == "cnh"
        else ("REGISTRO GERAL", "RG", "IDENTIDADE", "DOCUMENTO")
    )

    for index, line in enumerate(lines):
        normalized = line.normalized
        if not any(label in normalized for label in labels):
            continue

        candidates = [normalized]
        if index + 1 < len(lines):
            candidates.append(lines[index + 1].normalized)

        for candidate in candidates:
            values = re.findall(r"\b[A-Z0-9][A-Z0-9.\-/]{5,15}\b", candidate)
            values = [value for value in values if re.search(r"\d", value) and value not in labels]
            if values:
                source_line = line if candidate == normalized else lines[index + 1]
                return _field(values[-1], source_line)

    return _field(None, None)


def _looks_like_name(value: str) -> bool:
    words = re.findall(r"[A-ZÀ-Ü]{2,}", value.upper())
    return 2 <= len(words) <= 8 and not any(char.isdigit() for char in value)


def _is_document_label(value: str) -> bool:
    labels = (
        "VALIDADE", "VALIDITY", "EMISSAO", "ISSUE", "HABILITACAO", "NASCIMENTO",
        "FILIAÇÃO", "FILIACAO", "NACIONALIDADE", "NATURALIDADE", "ASSINATURA",
        "CATEGORIA", "REGISTRO", "DOCUMENTO", "IDENTIDADE", "CPF",
    )
    return any(label in value for label in labels)


def _bounding_box(points) -> tuple[float, float, float, float] | None:
    try:
        xs = [float(point[0]) for point in points]
        ys = [float(point[1]) for point in points]
    except (TypeError, ValueError, IndexError):
        return None

    return (min(xs), min(ys), max(xs), max(ys)) if xs and ys else None


def _nearby_lines_below(label: OcrLine, lines: list[OcrLine]) -> list[OcrLine]:
    if not label.box:
        return []

    left, top, right, bottom = label.box
    label_height = max(1.0, bottom - top)
    label_width = max(1.0, right - left)

    candidates: list[tuple[float, OcrLine]] = []
    for candidate in lines:
        if candidate is label or candidate.source != label.source or not candidate.box:
            continue

        candidate_left, candidate_top, candidate_right, _ = candidate.box
        vertical_gap = candidate_top - bottom
        if vertical_gap < -label_height * 0.25 or vertical_gap > label_height * 4:
            continue

        horizontal_overlap = max(0.0, min(right, candidate_right) - max(left, candidate_left))
        horizontally_aligned = horizontal_overlap > 0 or abs(candidate_left - left) <= label_width * 0.35
        if not horizontally_aligned:
            continue

        horizontal_gap = abs(candidate_left - left) / label_width
        candidates.append((vertical_gap + horizontal_gap, candidate))

    return [candidate for _, candidate in sorted(candidates, key=lambda item: item[0])]


def _valid_birth_date(match: re.Match[str]) -> str | None:
    value = f"{match.group(1)}/{match.group(2)}/{match.group(3)}"
    try:
        parsed = datetime.strptime(value, "%d/%m/%Y")
    except ValueError:
        return None

    if 1900 <= parsed.year < datetime.now().year:
        return parsed.strftime("%Y-%m-%d")

    return None


def _title_name(value: str) -> str:
    lowercase_words = {"DA", "DAS", "DE", "DO", "DOS", "E"}
    words = re.sub(r"\s+", " ", value.strip()).split(" ")
    return " ".join(
        word.lower() if index > 0 and word.upper() in lowercase_words else word.capitalize()
        for index, word in enumerate(words)
    )


def _valid_cpf(digits: str) -> bool:
    if len(digits) != 11 or digits == digits[0] * 11:
        return False

    for position in (9, 10):
        total = sum(int(digits[index]) * (position + 1 - index) for index in range(position))
        check = (total * 10) % 11
        check = 0 if check == 10 else check
        if check != int(digits[position]):
            return False

    return True
