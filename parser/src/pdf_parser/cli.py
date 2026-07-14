from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from pdf_parser.service import parse_pdf_file


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="crm-pdf-parser",
        description="Extracts text from PDF and returns CRM parser JSON.",
    )
    parser.add_argument("pdf_path", help="Path to PDF file.")

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    pdf_path = Path(args.pdf_path)

    if not pdf_path.exists():
        print(f'PDF file "{pdf_path}" does not exist.', file=sys.stderr)

        return 1

    if not pdf_path.is_file():
        print(f'Path "{pdf_path}" is not a file.', file=sys.stderr)

        return 1

    try:
        result = parse_pdf_file(pdf_path)
    except Exception as exception:
        print(f"Unable to parse PDF: {exception}", file=sys.stderr)

        return 1

    print(json.dumps(result, ensure_ascii=False, indent=2))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())