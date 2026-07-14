FROM python:3.12-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app/parser

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
    && rm -rf /var/lib/apt/lists/*

COPY parser/ /app/parser/

RUN pip install --no-cache-dir -e .

CMD ["uvicorn", "pdf_parser.api:app", "--host", "0.0.0.0", "--port", "8001"]