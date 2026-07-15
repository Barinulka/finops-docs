from __future__ import annotations


PARSER_VERSION = "pdf-parser/1.0.0"


def empty_result(raw_text: str, warnings: list[str] | None = None) -> dict:
    # Базовый ответ парсера. Его структура должна оставаться стабильной:
    # Symfony может безопасно читать эти ключи даже при неудачном разборе PDF.
    return {
        "parserVersion": PARSER_VERSION,
        "documentType": "unknown",
        "confidence": 0.0,
        "rawText": raw_text,
        # Имена полей сделаны в camelCase, потому что это внешний JSON-контракт,
        # а не внутренние имена Python-переменных.
        "fields": {
            "requestNumber": None,
            "requestDate": None,
            "contractNumber": None,
            "contractDate": None,
            "paymentAmount": None,
            "paymentCurrency": None,
            "paymentAmountRub": None,
            "exchangeRate": None,
            "exchangeRateRaw": "нет",
            "agencyFeePercent": None,
            "agencyFeeAmountRub": None,
            "totalAmountRub": None,
            "executionTermRaw": None,
            "executionDueDate": None,
            "paymentTermRaw": None,
            "paymentDueDate": None,
            "paymentType": None,
            "paymentTypeRaw": None,
            "beneficiaryBank": None,
            "invoiceNumber": None,
            "invoiceDate": None,
            "beneficiaryName": None,
            "beneficiaryAccount": None,
            "swiftCode": None,
            "paymentReference": None,
            "termsComment": None,
        },
        "warnings": warnings or [],
    }
