from __future__ import annotations

import os
from pathlib import Path
from tempfile import NamedTemporaryFile

from fastapi import FastAPI, File, HTTPException, UploadFile

from pdf_parser.service import parse_pdf_file


app = FastAPI(
    title="CRM PDF Parser",
    version="0.1.0",
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/parse")
async def parse(file: UploadFile = File(...)) -> dict:
    if file.filename is None or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files are supported.")

    temp_path: str | None = None

    try:
        content = await file.read()

        if content == b"":
            raise HTTPException(status_code=400, detail="Uploaded file is empty.")

        with NamedTemporaryFile(delete=False, suffix=".pdf") as temp_file:
            temp_file.write(content)
            temp_path = temp_file.name

        return parse_pdf_file(Path(temp_path))
    except HTTPException:
        raise
    except Exception as exception:
        raise HTTPException(status_code=422, detail=f"Unable to parse PDF: {exception}") from exception
    finally:
        if temp_path is not None and os.path.exists(temp_path):
            os.unlink(temp_path)