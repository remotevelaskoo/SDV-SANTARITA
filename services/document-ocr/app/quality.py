"""Preparação e diagnóstico de qualidade das imagens antes do OCR (AJ-006).

Mede, em memória, o que costuma derrubar a leitura: orientação, resolução,
iluminação, reflexo, foco, contraste e enquadramento. O resultado é um
conjunto de códigos objetivos, sem nenhum dado do documento, para que o
SDV oriente uma nova captura. Os limiares são provisórios até a
homologação com amostras autorizadas e nunca bloqueiam a leitura: a imagem
é lida mesmo assim e os códigos só explicam um resultado ruim.
"""

from __future__ import annotations

import io
from dataclasses import dataclass, field

import cv2
import numpy as np
from PIL import Image, ImageOps, UnidentifiedImageError


# Limiares provisórios (ver docstring do módulo).
MIN_SHORT_SIDE = 600
MIN_BRIGHTNESS = 60.0
MAX_BRIGHTNESS = 215.0
MAX_GLARE_RATIO = 0.03
MIN_SHARPNESS = 45.0
MIN_CONTRAST = 28.0
MAX_MARGIN_EDGE_RATIO = 0.05
ANALYSIS_WIDTH = 1000

EXIF_ORIENTATION_TAG = 274


@dataclass
class PreparedImage:
    source: str
    array: np.ndarray | None
    quality: dict = field(default_factory=dict)


def prepare(source: str, content: bytes) -> PreparedImage:
    """Aplica a orientação EXIF e mede a qualidade da imagem."""
    try:
        with Image.open(io.BytesIO(content)) as opened:
            orientation = opened.getexif().get(EXIF_ORIENTATION_TAG, 1)
            image = ImageOps.exif_transpose(opened).convert("RGB")
    except (UnidentifiedImageError, OSError, ValueError):
        return PreparedImage(source, None, {"source": source, "ok": False, "issues": ["imagem_invalida"], "metrics": {}, "exif_rotated": False})

    rgb = np.asarray(image)
    bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
    quality = assess(rgb)
    quality["source"] = source
    quality["exif_rotated"] = orientation not in (None, 1)

    return PreparedImage(source, bgr, quality)


def assess(rgb: np.ndarray) -> dict:
    height, width = rgb.shape[:2]
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)

    if width > ANALYSIS_WIDTH:
        scale = ANALYSIS_WIDTH / width
        gray = cv2.resize(gray, (ANALYSIS_WIDTH, max(1, int(height * scale))), interpolation=cv2.INTER_AREA)

    brightness = float(gray.mean())
    contrast = float(gray.std())
    glare = float((gray >= 250).mean())
    sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    margin_ratio = _margin_edge_ratio(gray)

    issues: list[str] = []
    if min(width, height) < MIN_SHORT_SIDE:
        issues.append("resolucao_baixa")
    if brightness < MIN_BRIGHTNESS:
        issues.append("escura")
    elif brightness > MAX_BRIGHTNESS:
        issues.append("clara_demais")
    if glare > MAX_GLARE_RATIO:
        issues.append("reflexo")
    if sharpness < MIN_SHARPNESS:
        issues.append("desfocada")
    if contrast < MIN_CONTRAST:
        issues.append("baixo_contraste")
    if margin_ratio is not None and margin_ratio > MAX_MARGIN_EDGE_RATIO:
        issues.append("possivelmente_cortado")

    return {
        "ok": not issues,
        "issues": issues,
        "metrics": {
            "width": int(width),
            "height": int(height),
            "brightness": round(brightness, 1),
            "contrast": round(contrast, 1),
            "glare_ratio": round(glare, 4),
            "sharpness": round(sharpness, 1),
            "margin_edge_ratio": None if margin_ratio is None else round(margin_ratio, 4),
        },
    }


def _margin_edge_ratio(gray: np.ndarray) -> float | None:
    """Fração das bordas detectadas que encostam na margem da foto.

    Documento inteiro, com fundo em volta, deixa a margem quase sem bordas;
    documento cortado leva texto e contornos até a beirada da imagem.
    """
    edges = cv2.Canny(gray, 80, 200)
    total = int(np.count_nonzero(edges))
    if total == 0:
        return None

    height, width = edges.shape
    band_y = max(2, height // 30)
    band_x = max(2, width // 30)
    margin = np.zeros_like(edges, dtype=bool)
    margin[:band_y, :] = True
    margin[-band_y:, :] = True
    margin[:, :band_x] = True
    margin[:, -band_x:] = True

    return float(np.count_nonzero(edges[margin])) / total
