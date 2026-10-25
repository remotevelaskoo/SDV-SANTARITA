from __future__ import annotations

import io
import logging
import os
import secrets
from contextlib import asynccontextmanager
from functools import lru_cache

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile, status
from PIL import Image, UnidentifiedImageError

from .extractor import DocumentExtractor


MAX_FILE_SIZE = 8 * 1024 * 1024
ALLOWED_TYPES = {"image/jpeg", "image/png", "image/webp"}
ALLOWED_DOCUMENTS = {"cnh", "rg_cin"}

logger = logging.getLogger("document_ocr")


@lru_cache(maxsize=1)
def extractor() -> DocumentExtractor:
    return DocumentExtractor()


@asynccontextmanager
async def lifespan(_app: FastAPI):
    # A primeira leitura carrega os modelos e pode passar do tempo limite do
    # SDV; aquecer na subida evita que o primeiro documento falhe por isso.
    if os.getenv("DOCUMENT_OCR_WARMUP", "1") == "1":
        try:
            blank = io.BytesIO()
            Image.new("RGB", (64, 64), "white").save(blank, format="PNG")
            extractor().extract("cnh", [("front", blank.getvalue())])
        except Exception:
            # Só o tipo do erro: nunca conteúdo de imagem ou documento.
            logger.warning("warmup_failed", exc_info=False)
    yield


app = FastAPI(
    title="SDV Document OCR",
    version="0.2.0",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
    lifespan=lifespan,
)


def authorize(authorization: str | None = Header(default=None)) -> None:
    expected = os.getenv("DOCUMENT_OCR_TOKEN", "")
    if expected and not secrets.compare_digest(authorization or "", f"Bearer {expected}"):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="unauthorized")


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/v1/extract", dependencies=[Depends(authorize)])
async def extract_document(
    document_type: str = Form(...),
    include_portrait: bool = Form(default=False),
    front: UploadFile = File(...),
    back: UploadFile | None = File(default=None),
) -> dict:
    if document_type not in ALLOWED_DOCUMENTS:
        raise HTTPException(status_code=422, detail="unsupported_document_type")

    images = [("front", await _validated_image(front))]
    if back is not None:
        images.append(("back", await _validated_image(back)))

    return extractor().extract(document_type, images, include_portrait=include_portrait)


async def _validated_image(upload: UploadFile) -> bytes:
    if upload.content_type not in ALLOWED_TYPES:
        raise HTTPException(status_code=422, detail="unsupported_image_type")

    content = await upload.read(MAX_FILE_SIZE + 1)
    if not content or len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=422, detail="invalid_image_size")

    try:
        with Image.open(io.BytesIO(content)) as image:
            if image.width * image.height > 20_000_000:
                raise HTTPException(status_code=422, detail="image_dimensions_too_large")
            image.verify()
    except (UnidentifiedImageError, OSError):
        raise HTTPException(status_code=422, detail="invalid_image_content") from None

    return content
