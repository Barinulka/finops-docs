from __future__ import annotations

import subprocess
import tempfile
from pathlib import Path

from pypdf import PdfReader


def extract_text_from_pdf(path: Path) -> str:
    text = extract_text_layer_from_pdf(path)

    if text.strip():
        return text

    return extract_text_with_ocr(path)


def extract_text_layer_from_pdf(path: Path) -> str:
    reader = PdfReader(path)
    parts: list[str] = []

    for page in reader.pages:
        # pypdf достает текст только из текстового слоя PDF.
        # Если PDF является сканом/картинкой, здесь чаще всего будет пустая строка.
        text = page.extract_text() or ""
        text = text.strip()

        if text:
            parts.append(text)

    # Разделяем страницы пустой строкой, чтобы regex мог искать фразы через переносы,
    # но при этом было видно границы страниц при отладке rawText.
    return "\n\n".join(parts)


def extract_text_with_ocr(path: Path) -> str:
    with tempfile.TemporaryDirectory(prefix="crm-pdf-ocr-") as temp_dir:
        temp_path = Path(temp_dir)
        page_prefix = temp_path / "page"

        subprocess.run(
            [
                "pdftoppm",
                "-r",
                "220",
                "-png",
                str(path),
                str(page_prefix),
            ],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )

        page_images = sorted(temp_path.glob("page-*.png"))

        if page_images == []:
            return ""

        parts: list[str] = []

        for page_image in page_images:
            result = subprocess.run(
                [
                    "tesseract",
                    str(page_image),
                    "stdout",
                    "-l",
                    "rus+eng",
                    "--psm",
                    "6",
                ],
                check=True,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
            )

            text = result.stdout.strip()

            if text:
                parts.append(text)

        return "\n\n".join(parts)
