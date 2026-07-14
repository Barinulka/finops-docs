from __future__ import annotations

from pathlib import Path

from pypdf import PdfReader


def extract_text_from_pdf(path: Path) -> str:
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
