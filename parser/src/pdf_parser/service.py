from __future__ import annotations

from pathlib import Path

from pdf_parser.extract_text import extract_text_from_pdf
from pdf_parser.parse_fields import parse_fields
from pdf_parser.result import empty_result


def parse_pdf_file(pdf_path: Path) -> dict:
    warnings: list[str] = []

    raw_text = extract_text_from_pdf(pdf_path)

    if raw_text.strip() == "":
        warnings.append("PDF text was not extracted. The document may be scanned or image-based.")

    fields, field_warnings, document_type, confidence = parse_fields(raw_text)
    warnings.extend(field_warnings)

    result = empty_result(raw_text=raw_text, warnings=warnings)
    result["documentType"] = document_type
    result["confidence"] = confidence
    result["fields"] = fields

    return result