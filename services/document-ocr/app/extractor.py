from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass
from datetime import datetime
from threading import Lock
from typing import Iterable

import numpy as np
from rapidocr import RapidOCR

from .portrait import PortraitExtractor
from .quality import PreparedImage, prepare


ENGINE_NAME = "rapidocr"
MODEL_VERSION = "PP-OCRv6-small"

# Uma leitura com menos linhas úteis ou confiança média abaixo disto é
# repetida com a imagem girada 90°, 180° e 270° (foto tirada de lado).
MIN_USEFUL_LINES = 4
MIN_MEAN_CONFIDENCE = 0.55
LOW_FIELD_CONFIDENCE = 0.6

TYPE_KEYWORDS = {
    "cnh": ("CARTEIRA NACIONAL DE HABILITACAO", "HABILITACAO", "PERMISSAO PARA DIRIGIR", "CAT HAB", "1 HABILITACAO", "ACC"),
    "rg_cin": ("REGISTRO GERAL", "CARTEIRA DE IDENTIDADE", "CEDULA DE IDENTIDADE", "IDENTIDADE NACIONAL", "SECRETARIA DE SEGURANCA", "INSTITUTO DE IDENTIFICACAO", "FILIACAO", "NATURALIDADE"),
}


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
        quality_reports: list[dict] = []
        rotations: dict[str, int] = {}

        for source, content in image_list:
            prepared = prepare(source, content)
            quality_reports.append(prepared.quality)
            source_lines, rotation = self._recognize(prepared, content)
            lines.extend(source_lines)
            rotations[source] = rotation

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

        fields = parse_fields(document_type, lines)
        detected_type = detect_document_type(lines)

        return {
            "status": "completed",
            "engine": ENGINE_NAME,
            "model_version": MODEL_VERSION,
            "document_type": document_type,
            "fields": fields,
            "portrait": portrait,
            "line_count": len(lines),
            "diagnostics": diagnose(
                document_type,
                [source for source, _ in image_list],
                quality_reports,
                lines,
                fields,
                detected_type,
                rotations,
            ),
        }

    def _recognize(self, prepared: PreparedImage, original: bytes) -> tuple[list[OcrLine], int]:
        """Lê a imagem já orientada pelo EXIF; se a leitura vier pobre, tenta girada."""
        if prepared.array is None:
            return self._run(original, prepared.source), 0

        best_lines = self._run(prepared.array, prepared.source)
        best_rotation = 0
        sideways = _looks_sideways(best_lines)
        if _good_enough(best_lines) and not sideways:
            return best_lines, 0

        for rotation, turns in ((90, 3), (270, 1), (180, 2)):
            rotated = np.ascontiguousarray(np.rot90(prepared.array, turns))
            candidate = self._run(rotated, prepared.source)
            if _reading_score(candidate) > _reading_score(best_lines):
                best_lines, best_rotation = candidate, rotation
            if _good_enough(best_lines) and not _looks_sideways(best_lines):
                break

        return best_lines, best_rotation

    def _run(self, image, source: str) -> list[OcrLine]:
        with self._lock:
            result = self._engine(image)

        if not result or not getattr(result, "txts", None):
            return []

        boxes = result.boxes if getattr(result, "boxes", None) is not None else ()
        scores = result.scores if getattr(result, "scores", None) is not None else ()
        lines = []
        for index, (text, score) in enumerate(zip(result.txts, scores, strict=False)):
            box = _bounding_box(boxes[index]) if index < len(boxes) else None
            lines.append(OcrLine(text=text, confidence=float(score), source=source, box=box))

        return lines


def _useful(lines: list[OcrLine]) -> list[OcrLine]:
    return [line for line in lines if len(re.sub(r"[^A-Z0-9]", "", line.normalized)) >= 3]


def _reading_score(lines: list[OcrLine]) -> float:
    return sum(line.confidence for line in _useful(lines))


def _looks_sideways(lines: list[OcrLine]) -> bool:
    """Foto de lado: a maior parte das linhas de texto fica mais alta que larga."""
    boxes = [line.box for line in _useful(lines) if line.box]
    if len(boxes) < 3:
        return False

    vertical = sum(1 for left, top, right, bottom in boxes if (bottom - top) > 1.5 * (right - left))
    return vertical / len(boxes) > 0.5


def _good_enough(lines: list[OcrLine]) -> bool:
    useful = _useful(lines)
    if len(useful) < MIN_USEFUL_LINES:
        return False

    return sum(line.confidence for line in useful) / len(useful) >= MIN_MEAN_CONFIDENCE


def detect_document_type(lines: list[OcrLine]) -> str | None:
    scores = {
        document_type: sum(1 for line in lines for keyword in keywords if _has_label(line.normalized, keyword))
        for document_type, keywords in TYPE_KEYWORDS.items()
    }
    best = max(scores, key=scores.get)
    if scores[best] == 0 or list(scores.values()).count(scores[best]) > 1:
        return None

    return best


def diagnose(
    document_type: str,
    sources: list[str],
    quality_reports: list[dict],
    lines: list[OcrLine],
    fields: dict,
    detected_type: str | None,
    rotations: dict[str, int],
) -> dict:
    """Separa por que a leitura falhou, sem incluir texto lido nem valores.

    Etapas: "capture" (arquivo inválido), "quality" (imagem ruim e nada
    lido), "ocr" (nenhum texto reconhecido), "interpretation" (texto lido,
    campos não localizados), "partial" (parte dos campos) e "ok".
    """
    quality_issues = sorted({issue for report in quality_reports for issue in report.get("issues", [])})
    found = [name for name, value in fields.items() if value.get("value")]
    issues: list[str] = []

    if "imagem_invalida" in quality_issues and not lines:
        stage = "capture"
    elif not _useful(lines):
        stage = "quality" if quality_issues else "ocr"
        issues.append("sem_texto")
    elif not found:
        stage = "interpretation"
        issues.append("campos_nao_localizados")
    elif len(found) < len(fields):
        stage = "partial"
    else:
        stage = "ok"

    if detected_type is not None and detected_type != document_type:
        issues.append("tipo_divergente")
    if document_type == "rg_cin" and "back" not in sources and len(found) < len(fields):
        issues.append("verso_recomendado")
    if _invalid_cpf_seen(lines):
        issues.append("cpf_digito_invalido")
    if any(value.get("value") and (value.get("confidence") or 0) < LOW_FIELD_CONFIDENCE for value in fields.values()):
        issues.append("confianca_baixa")

    recapture = stage in ("capture", "quality", "ocr", "interpretation") or "tipo_divergente" in issues or (
        bool(quality_issues) and stage != "ok"
    )

    return {
        "stage": stage,
        "issues": issues,
        "quality_issues": quality_issues,
        "quality": quality_reports,
        "fields_found": found,
        "detected_type": detected_type,
        "rotation_applied": {source: degrees for source, degrees in rotations.items() if degrees},
        "exif_rotated": [report["source"] for report in quality_reports if report.get("exif_rotated")],
        "recapture_recommended": recapture,
    }


def _invalid_cpf_seen(lines: list[OcrLine]) -> bool:
    pattern = re.compile(r"(?<!\d)(\d{3}[.\s]?\d{3}[.\s]?\d{3}[-\s]?\d{2})(?!\d)")
    candidates = [re.sub(r"\D", "", match) for line in lines for match in pattern.findall(line.normalized)]

    return bool(candidates) and not any(_valid_cpf(digits) for digits in candidates)


def parse_fields(document_type: str, lines: list[OcrLine]) -> dict:
    return {
        "name": _name_field(lines),
        "cpf": _cpf_field(lines),
        "birth_date": _birth_date_field(lines),
        "document_number": _document_number_field(document_type, lines),
    }


def _field(value: str | None, line: OcrLine | None, validated: bool = False) -> dict:
    return {
        "value": value,
        "confidence": round(line.confidence, 4) if value and line else None,
        "source": line.source if value and line else None,
        "validated": bool(value) and validated,
    }


def _has_label(text: str, label: str) -> bool:
    """Rótulo como palavra inteira: "RG" não casa dentro de "ORGAO"."""
    return re.search(rf"(?<![A-Z0-9]){re.escape(label)}(?![A-Z0-9])", text) is not None


def _name_field(lines: list[OcrLine]) -> dict:
    labels = ("NOME COMPLETO", "NOME E SOBRENOME", "NOME", "NAME")

    for index, line in enumerate(lines):
        normalized = line.normalized

        for label in labels:
            if _has_label(normalized, label) and line.box:
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
    formatted = re.compile(r"\d{3}\.\d{3}\.\d{3}-\d{2}")
    registry_labels = ("REGISTRO", "N REGISTRO", "RENACH", "ESPELHO")

    # Ordem de preferência: linha com o rótulo CPF (ou logo abaixo dele),
    # número no formato 000.000.000-00 e, por último, onze dígitos soltos.
    # O registro da CNH também tem onze dígitos e pode passar no cálculo do
    # CPF, por isso linhas de registro nunca entram como número solto.
    labeled: list[OcrLine] = []
    for line in lines:
        if _has_label(line.normalized, "CPF"):
            labeled.append(line)
            labeled.extend(_nearby_lines_below(line, lines)[:2])

    ordered = labeled + [line for line in lines if formatted.search(line.normalized)] + [
        line for line in lines if not any(_has_label(line.normalized, label) for label in registry_labels)
    ]

    for line in ordered:
        match = pattern.search(line.normalized)
        if not match:
            continue

        digits = re.sub(r"\D", "", match.group(1))
        if _valid_cpf(digits):
            return _field(f"{digits[:3]}.{digits[3:6]}.{digits[6:9]}-{digits[9:]}", line, validated=True)

    return _field(None, None)


def _birth_date_field(lines: list[OcrLine]) -> dict:
    pattern = re.compile(r"\b(\d{2})[/.\-](\d{2})[/.\-](\d{4})\b")
    labels = ("DATA DE NASCIMENTO", "DATA NASCIMENTO", "NASCIMENTO", "NASC", "DATE OF BIRTH")

    # Na CNH atual, o rótulo e o valor normalmente são blocos separados.
    # A posição visual evita confundir nascimento com validade, emissão ou
    # primeira habilitação, que também aparecem como datas no documento.
    for line in lines:
        if not line.box or not any(_has_label(line.normalized, label) for label in labels):
            continue

        match = pattern.search(line.normalized)
        if match and (value := _valid_birth_date(match)):
            return _field(value, line, validated=True)

        for candidate in _nearby_lines_below(line, lines):
            match = pattern.search(candidate.normalized)
            if match and (value := _valid_birth_date(match)):
                return _field(value, candidate, validated=True)

    prioritized = sorted(
        lines,
        key=lambda line: 0 if any(_has_label(line.normalized, label) for label in labels) else 1,
    )

    for line in prioritized:
        match = pattern.search(line.normalized)
        if not match:
            continue

        if value := _valid_birth_date(match):
            return _field(value, line, validated=True)

    return _field(None, None)


def _document_number_field(document_type: str, lines: list[OcrLine]) -> dict:
    labels = (
        ("REGISTRO", "N REGISTRO", "NUMERO REGISTRO", "N DA CNH")
        if document_type == "cnh"
        else ("REGISTRO GERAL", "RG", "IDENTIDADE", "DOCUMENTO")
    )

    for index, line in enumerate(lines):
        normalized = line.normalized
        if not any(_has_label(normalized, label) for label in labels):
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
