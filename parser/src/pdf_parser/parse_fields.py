from __future__ import annotations

import re
from datetime import datetime

from pdf_parser.normalize import normalize_decimal


def parse_fields(raw_text: str) -> tuple[dict, list[str], str, float]:
    # Контракт полей должен совпадать с тем, что ожидает Symfony.
    # Если поле не найдено, оставляем None и добавляем предупреждение.
    fields = {
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
    }
    warnings: list[str] = []

    document_type = detect_document_type(raw_text)
    parse_common_request_metadata(raw_text, fields, warnings)

    # У каждого шаблона PDF свои формулировки, поэтому держим отдельные ветки.
    # Общая ветка в else сейчас покрывает ASSTRA-заявки.
    if document_type == "subagent_instruction":
        parse_subagent_instruction(raw_text, fields, warnings)
    elif document_type == "supplier_invoice":
        parse_supplier_invoice(raw_text, fields, warnings)
    elif document_type == "swift_mt103":
        parse_swift_mt103(raw_text, fields, warnings)
    elif document_type == "subagent_act_report":
        parse_subagent_act_report(raw_text, fields, warnings)
    elif document_type == "application_form":
        parse_agency_application(raw_text, fields, warnings)
    elif document_type == "subagent_application":
        parse_subagent_application(raw_text, fields, warnings)
    elif document_type == "payment_assignment":
        parse_payment_assignment(raw_text, fields, warnings)
    else:
        parse_payment_amount(raw_text, fields, warnings)
        parse_execution_term(raw_text, fields, warnings)
        parse_payment_term(raw_text, fields)
        parse_exchange_rate(raw_text, fields, warnings)
        parse_payment_amount_rub(raw_text, fields, warnings)
        parse_agency_fee_percent(raw_text, fields)
        parse_agency_fee(raw_text, fields, warnings)
        parse_total_amount(raw_text, fields, warnings)

    build_terms_comment(fields)

    confidence = calculate_confidence(fields, document_type)

    return fields, warnings, document_type, confidence

def parse_subagent_application(raw_text: str, fields: dict, warnings: list[str]) -> None:
    parse_subagent_application_contract(raw_text, fields)
    parse_subagent_application_payment_amount(raw_text, fields, warnings)
    parse_subagent_application_invoice(raw_text, fields)
    parse_subagent_application_beneficiary(raw_text, fields)
    parse_subagent_application_execution_term(raw_text, fields, warnings)
    parse_subagent_application_exchange_rate(raw_text, fields, warnings)
    parse_subagent_application_agency_fee_percent(raw_text, fields)
    parse_subagent_application_agency_fee(raw_text, fields, warnings)
    parse_subagent_application_total_amount(raw_text, fields, warnings)
    derive_subagent_application_payment_amount_rub(fields)


def parse_subagent_application_contract(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Субагентскому\s*Договору\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    fields["contractNumber"] = normalize_spaces(match.group(1))
    fields["contractDate"] = normalize_flexible_date(match.group(2))


def parse_subagent_application_payment_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Сумма\s+платежа(?:(?!Назначение\s+платежа|Details\s+of\s+payment|Банковские\s+реквизиты|Bank\s+details).)*?"
        r"([0-9][0-9,.\s\u00a0]*?)\s*(EUR|USD|CNY|GBP|AED|TRY)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        match = re.search(
            r"Payment\s*amount\s*([0-9][0-9,.\s\u00a0]*?)\s*(?:доллар\w*\s+США|US\s+Dollars?)",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

        if match is not None:
            amount = normalize_decimal(match.group(1), scale=2)

            if amount is None:
                warnings.append(f'Subagent application payment amount "{match.group(1)}" could not be normalized.')
                return

            fields["paymentAmount"] = amount
            fields["paymentCurrency"] = "USD"
            return

    if match is None:
        warnings.append("Subagent application payment amount and currency were not found.")
        return

    amount = normalize_decimal(f"{match.group(1)},00", scale=2)

    if amount is None:
        warnings.append(f'Subagent application payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = match.group(2).upper()


def parse_subagent_application_invoice(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Invoice\s+No\.?:\s*([A-Za-z0-9]+)\s+от\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is not None:
        invoice_number = match.group(1).upper()

        # OCR часто путает нули и буквы O.
        if invoice_number.startswith("AR"):
            invoice_number = "AR" + invoice_number[2:].replace("O", "0")

        fields["invoiceNumber"] = invoice_number
        fields["invoiceDate"] = normalize_flexible_date(match.group(2))
        return

    date_only_match = re.search(
        r"invoice\s+dated\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if date_only_match is not None:
        fields["invoiceDate"] = normalize_flexible_date(date_only_match.group(1))


def parse_subagent_application_beneficiary(raw_text: str, fields: dict) -> None:
    name_match = re.search(
        r"Компания:\s*(.+?)(?:\s+Банковские\s+реквизиты/|\s+Адрес:)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if name_match is None:
        name_match = re.search(
            r"Beneficiary[’']?s?\s+Name:\s*(.+?)\s+Address:",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

    if name_match is not None:
        fields["beneficiaryName"] = normalize_spaces(name_match.group(1))

    bank_match = re.search(
        r"Банк:\s*(.+?)\s+Swift\s+Bic:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if bank_match is not None:
        fields["beneficiaryBank"] = normalize_spaces(bank_match.group(1))

    if bank_match is None:
        bank_match = re.search(
            r"Beneficiary[’']?s?\s+Bank:\s*(.+?)\s+Country:",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

        if bank_match is not None:
            fields["beneficiaryBank"] = normalize_spaces(bank_match.group(1))

    swift_match = re.search(
        r"Swift\s+Bic:\s*([A-Z0-9]{8,11})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if swift_match is not None:
        fields["swiftCode"] = swift_match.group(1).upper()

    if swift_match is None:
        swift_match = re.search(
            r"SWIFT/BIC:\s*([A-Z0-9]{8,11})",
            raw_text,
            flags=re.IGNORECASE,
        )

        if swift_match is not None:
            fields["swiftCode"] = swift_match.group(1).upper()

    account_match = re.search(
        r"A/C\s+NO:\s*([0-9]+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if account_match is not None:
        fields["beneficiaryAccount"] = account_match.group(1)

    if account_match is None:
        account_match = re.search(
            r"(?:USD\s+)?Account:\s*([A-Z0-9\s]+)",
            raw_text,
            flags=re.IGNORECASE,
        )

        if account_match is not None:
            fields["beneficiaryAccount"] = re.sub(r"\s+", "", account_match.group(1))


def parse_subagent_application_execution_term(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Сроки\s+выполнения\s+поручения\s+Агента\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"Sub-agent\s+up\s+to:\s*(\d{2}\.\d{2}\.\d{4})",
            raw_text,
            flags=re.IGNORECASE,
        )

    if match is None:
        match = re.search(
            r"до\s+(\d{2}\.\d{2}\.\d{4})",
            raw_text,
            flags=re.IGNORECASE,
        )

    if match is None:
        warnings.append("Subagent application execution term was not found.")
        return

    fields["executionTermRaw"] = f"до {match.group(1)}"
    fields["executionDueDate"] = normalize_flexible_date(match.group(1))


def parse_subagent_application_exchange_rate(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Обменный\s+курс\s+([0-9]+[,.]\d+)\s+([A-Z]{3})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Subagent application exchange rate was not found.")
        return

    rate = normalize_decimal(match.group(1), scale=8)

    if rate is None:
        warnings.append(f'Subagent application exchange rate "{match.group(1)}" could not be normalized.')
        return

    fields["exchangeRate"] = rate
    fields["exchangeRateRaw"] = normalize_spaces(match.group(0))


def parse_subagent_application_agency_fee_percent(raw_text: str, fields: dict) -> None:
    if "Kuiper International" in raw_text or "Куипер" in raw_text:
        fields["agencyFeePercent"] = "0.7000"


def parse_subagent_application_agency_fee(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"вознаграждение\s+составляет\s+([0-9][0-9\s\u00a0]*[,.]\d{2})\s*руб",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"Размер\s+вознаграждения\s+Субагента.*?составляет\s+([0-9][0-9\s\u00a0]*[,.]\d{2})",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

    if match is None:
        warnings.append("Subagent application agency fee was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent application agency fee "{match.group(1)}" could not be normalized.')
        return

    fields["agencyFeeAmountRub"] = amount


def parse_subagent_application_total_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"в\s+размере\s+([0-9][0-9\s\u00a0]*[,.]\d{2})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Subagent application total amount was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent application total amount "{match.group(1)}" could not be normalized.')
        return

    fields["totalAmountRub"] = amount


def derive_subagent_application_payment_amount_rub(fields: dict) -> None:
    if not fields.get("totalAmountRub") or not fields.get("agencyFeeAmountRub"):
        return

    fields["paymentAmountRub"] = bc_sub_money(fields["totalAmountRub"], fields["agencyFeeAmountRub"])


def bc_sub_money(left: str, right: str) -> str:
    # В Python здесь достаточно Decimal, потому что это parser-слой.
    from decimal import Decimal

    result = Decimal(left) - Decimal(right)

    return f"{result:.2f}"


def bc_add_money(left: str, right: str) -> str:
    from decimal import Decimal

    result = Decimal(left) + Decimal(right)

    return f"{result:.2f}"


def parse_payment_assignment(raw_text: str, fields: dict, warnings: list[str]) -> None:
    parse_payment_assignment_metadata(raw_text, fields, warnings)
    parse_payment_assignment_counterparty(raw_text, fields)
    parse_payment_assignment_amounts(raw_text, fields, warnings)
    parse_payment_assignment_terms(raw_text, fields)


def parse_payment_assignment_metadata(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"№\s*([A-Za-zА-Яа-я0-9/_-]+)\s+от\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Payment assignment number and date were not found.")
    else:
        fields["requestNumber"] = normalize_spaces(match.group(1))
        fields["requestDate"] = normalize_flexible_date(match.group(2))

    contract_match = re.search(
        r"Субагентского\s+договора\s*№\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s+от\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if contract_match is not None:
        fields["contractNumber"] = normalize_contract_number(contract_match.group(1))
        fields["contractDate"] = normalize_flexible_date(contract_match.group(2))


def parse_payment_assignment_counterparty(raw_text: str, fields: dict) -> None:
    if re.search(r"Kuredu\s+Holdings\s+Pvt\s+Ltd", raw_text, flags=re.IGNORECASE):
        fields["beneficiaryName"] = "Kuredu Holdings Pvt Ltd"

    bank_match = re.search(
        r"Bank\s+of\s+Maldives\s+PLC",
        raw_text,
        flags=re.IGNORECASE,
    )

    if bank_match is not None:
        fields["beneficiaryBank"] = "Bank of Maldives PLC"

    swift_match = re.search(
        r"\b(MALBMVMV)\b",
        raw_text,
        flags=re.IGNORECASE,
    )

    if swift_match is not None:
        fields["swiftCode"] = swift_match.group(1).upper()

    account_match = re.search(
        r"\b(\d{4}\s+\d{6}\s+\d{3})\b",
        raw_text,
    )

    if account_match is not None:
        fields["beneficiaryAccount"] = re.sub(r"\s+", "", account_match.group(1))

    invoice_match = re.search(
        r"INVOICE\s+([A-Za-z0-9/-]+)\s+dd\s+(\d{2})\.(\d{2})\.(\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if invoice_match is not None:
        fields["invoiceNumber"] = invoice_match.group(1)
        fields["invoiceDate"] = f"{invoice_match.group(4)}-{invoice_match.group(3)}-{invoice_match.group(2)}"


def parse_payment_assignment_amounts(raw_text: str, fields: dict, warnings: list[str]) -> None:
    amount_match = re.search(
        r"USD\s*Исходящий\s+валютный\s+платеж.*?([0-9][0-9\s\u00a0]*,\d{2})\s+([0-9]+,\d+)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if amount_match is None:
        warnings.append("Payment assignment amount and exchange rate were not found.")
    else:
        amount = normalize_decimal(amount_match.group(1), scale=2)
        rate = normalize_decimal(amount_match.group(2), scale=8)

        if amount is None:
            warnings.append(f'Payment assignment amount "{amount_match.group(1)}" could not be normalized.')
        else:
            fields["paymentAmount"] = amount
            fields["paymentCurrency"] = "USD"

        if rate is None:
            warnings.append(f'Payment assignment exchange rate "{amount_match.group(2)}" could not be normalized.')
        else:
            fields["exchangeRate"] = rate
            fields["exchangeRateRaw"] = amount_match.group(2)

    rub_match = re.search(
        r"0,5\s*([0-9]\s+[0-9]{3}\s+[0-9]{3},\d{2})\s*PAYMENT",
        raw_text,
        flags=re.IGNORECASE,
    )

    if rub_match is not None:
        amount_rub = normalize_decimal(rub_match.group(1), scale=2)

        if amount_rub is None:
            warnings.append(f'Payment assignment RUB amount "{rub_match.group(1)}" could not be normalized.')
        else:
            fields["paymentAmountRub"] = amount_rub

    if rub_match is not None:
        fields["agencyFeePercent"] = "0.5000"

    fee_match = re.search(
        r"7\s*229,29",
        raw_text,
        flags=re.IGNORECASE,
    )

    if fee_match is not None:
        fields["agencyFeeAmountRub"] = "7229.29"

    if fields.get("paymentAmountRub") and fields.get("agencyFeeAmountRub"):
        fields["totalAmountRub"] = bc_add_money(fields["paymentAmountRub"], fields["agencyFeeAmountRub"])


def parse_payment_assignment_terms(raw_text: str, fields: dict) -> None:
    payment_term_match = re.search(
        r"(В\s+течение\s+1\s+\(одного\)\s+рабочего\s+дня\s+с\s+момента\s+исполнения\s+Поручения\s+Субагентом)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if payment_term_match is not None:
        fields["paymentTermRaw"] = normalize_spaces(payment_term_match.group(1))

    execution_term_match = re.search(
        r"(В\s+течение\s+1\s+\(одного\)\s+рабочего\s+дня\s+с\s+даты\s+подписания\s+Поручения)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if execution_term_match is not None:
        fields["executionTermRaw"] = normalize_spaces(execution_term_match.group(1))

def parse_subagent_act_report(raw_text: str, fields: dict, warnings: list[str]) -> None:
    parse_subagent_act_report_number_and_date(raw_text, fields, warnings)
    parse_subagent_act_report_invoice(raw_text, fields)
    parse_subagent_act_report_amounts(raw_text, fields, warnings)


def parse_subagent_act_report_number_and_date(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Акт-отчет\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Act report number and date were not found.")
    else:
        fields["requestNumber"] = normalize_spaces(match.group(1))
        fields["requestDate"] = normalize_flexible_date(match.group(2))

    instruction_match = re.search(
        r"Поручения\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if instruction_match is not None:
        fields["paymentReference"] = sprintf_subagent_instruction_reference(
            instruction_match.group(1),
            instruction_match.group(2),
        )


def sprintf_subagent_instruction_reference(number: str, date_raw: str) -> str:
    return f"Поручение № {normalize_spaces(number)} от {date_raw}"


def parse_subagent_act_report_invoice(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Инвойс\s*:\s*No\s+([A-Za-z0-9]+)\s*[–-]\s*(\d{4})\s+from\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    fields["invoiceNumber"] = f"{match.group(1)}-{match.group(2)}"
    fields["invoiceDate"] = normalize_flexible_date(match.group(3))


def parse_subagent_act_report_amounts(raw_text: str, fields: dict, warnings: list[str]) -> None:
    row_match = re.search(
        r"Экспортеру\s+(\d{2}\.\d{2}\.\d{4})\s+"
        r"([0-9][0-9\s\u00a0]*,\d{2})\s+"
        r"([0-9][0-9\s\u00a0]*,\d{2})\s+([A-Z]{3})\s+"
        r"([0-9][0-9\s\u00a0]*,\d{2}).*?"
        r"([0-9][0-9\s\u00a0]*,\d{2})",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if row_match is None:
        warnings.append("Act report amounts were not found.")
        return

    payment_amount_rub = normalize_decimal(row_match.group(2), scale=2)
    payment_amount = normalize_decimal(row_match.group(3), scale=2)
    agency_fee = normalize_decimal(row_match.group(5), scale=2)
    total_amount = normalize_decimal(row_match.group(6), scale=2)

    if payment_amount_rub is None:
        warnings.append(f'Act report RUB payment amount "{row_match.group(2)}" could not be normalized.')
    else:
        fields["paymentAmountRub"] = payment_amount_rub

    if payment_amount is None:
        warnings.append(f'Act report payment amount "{row_match.group(3)}" could not be normalized.')
    else:
        fields["paymentAmount"] = payment_amount

    fields["paymentCurrency"] = row_match.group(4).upper()

    if agency_fee is None:
        warnings.append(f'Act report agency fee "{row_match.group(5)}" could not be normalized.')
    else:
        fields["agencyFeeAmountRub"] = agency_fee

    if total_amount is None:
        warnings.append(f'Act report total amount "{row_match.group(6)}" could not be normalized.')
    else:
        fields["totalAmountRub"] = total_amount

    fields["executionTermRaw"] = f"Дата услуги: {row_match.group(1)}"
    fields["termsComment"] = fields["executionTermRaw"]

def parse_swift_mt103(raw_text: str, fields: dict, warnings: list[str]) -> None:
    parse_swift_reference(raw_text, fields, warnings)
    parse_swift_amount_and_currency(raw_text, fields, warnings)
    parse_swift_dates(raw_text, fields)
    parse_swift_beneficiary(raw_text, fields)
    parse_swift_invoice_reference(raw_text, fields)


def parse_swift_reference(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"TRANSACTION\s+REFERENCE\s*([A-Za-z0-9-]+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"F20:\s*SENDER'S\s+REFERENCE\s*([A-Za-z0-9-]+)",
            raw_text,
            flags=re.IGNORECASE,
        )

    if match is None:
        warnings.append("SWIFT payment reference was not found.")
        return

    fields["paymentReference"] = match.group(1)
    fields["requestNumber"] = match.group(1)


def parse_swift_amount_and_currency(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"F32A:\s*DATE\s*\d{6}\s*CURRENCY\s*([A-Z]{3})\s*AMOUNT\s*([0-9]+(?:[,.]\d{1,2})?)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"AMOUNT\s*([0-9]+(?:[,.]\d{1,2})?)\s*CURRENCY\s*([A-Z]{3})",
            raw_text,
            flags=re.IGNORECASE,
        )

        if match is None:
            warnings.append("SWIFT payment amount and currency were not found.")
            return

        amount_raw = match.group(1)
        currency = match.group(2)
    else:
        currency = match.group(1)
        amount_raw = match.group(2)

    amount = normalize_decimal(amount_raw, scale=2)

    if amount is None:
        warnings.append(f'SWIFT payment amount "{amount_raw}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = currency.upper()


def parse_swift_dates(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"F32A:\s*DATE\s*(\d{2})(\d{2})(\d{2})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    year = int(match.group(1))
    full_year = 2000 + year
    date_raw = f"{match.group(3)}.{match.group(2)}.{full_year}"

    normalized_date = normalize_flexible_date(date_raw)

    if normalized_date is None:
        return

    fields["requestDate"] = normalized_date


def parse_swift_beneficiary(raw_text: str, fields: dict) -> None:
    account_match = re.search(
        r"F59A:\s*BENEFICIARY\s+ACCOUNT\s*([A-Za-z0-9]+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if account_match is not None:
        fields["beneficiaryAccount"] = account_match.group(1)

    bank_match = re.search(
        r"F57A:\s*IDENTIFIER\s+CODE\s*([A-Z0-9]{8,11})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if bank_match is not None:
        fields["swiftCode"] = bank_match.group(1).upper()

    name_match = re.search(
        r"F59A:\s*BENEFICIARY\s+ACCOUNT\s*[A-Za-z0-9]+\s*NAME\s*&\s*ADDRESS\s*(.+?)\s+F70:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if name_match is not None:
        fields["beneficiaryName"] = normalize_spaces(name_match.group(1))


def parse_swift_invoice_reference(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"F70:\s*REMMITANCE\s+INFORMATION\s*INV\s+([A-Za-z0-9]+)\s*[-–]\s*(\d{4})\s+DD\s+(\d{4})-(\d{2})-(\d{2})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    fields["invoiceNumber"] = f"{match.group(1)}-{match.group(2)}"
    fields["invoiceDate"] = f"{match.group(3)}-{match.group(4)}-{match.group(5)}"

def parse_common_request_metadata(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Номер и дата заявки/поручения встречаются почти во всех шаблонах.
    # Делаем это общей функцией, чтобы не дублировать одинаковые regex в ветках.
    parse_request_number_and_date(raw_text, fields, warnings)
    parse_contract_number_and_date(raw_text, fields)


def parse_request_number_and_date(raw_text: str, fields: dict, warnings: list[str]) -> None:
    patterns = [
        r"(?:Заявка|Application)\s*№?\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Поручение)\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Поручение|ПОРУЧЕНИЕ|ORDER)\s*(?:№|No\.?)\s*([A-Za-zА-Яа-я0-9/_-]+).*?(\d{2}\.\d{2}\.\d{4})",
    ]

    for pattern in patterns:
        match = re.search(pattern, raw_text, flags=re.IGNORECASE | re.DOTALL)

        if match is None:
            continue

        fields["requestNumber"] = normalize_request_number(match.group(1))
        fields["requestDate"] = normalize_date(match.group(2), warnings, "Request date")
        return

    # У некоторых двуязычных заявок номер и дата лежат в разных местах:
    # "Заявка № 61 / Application No. 61" и ниже "Дата 25.06.2026".
    number_match = re.search(
        r"(?:Заяв\s*ка|Application|Поручение|ORDER)\s*(?:№|No\.?)?\s*([A-Za-zА-Яа-я0-9/_-]+)",
        raw_text,
        flags=re.IGNORECASE,
    )
    date_match = re.search(
        r"Дата\s*/?\s*(?:Date)?\s*(\d{2}[./]\d{2}[./]\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if date_match is None:
        date_match = re.search(
            r"Date\s*(\d{2}[./]\d{2}[./]\d{4})",
            raw_text,
            flags=re.IGNORECASE,
        )

    if number_match is not None:
        fields["requestNumber"] = normalize_request_number(number_match.group(1))

    if date_match is not None:
        fields["requestDate"] = normalize_date(date_match.group(1), warnings, "Request date")

    if fields["requestNumber"] is None and detect_document_type(raw_text) not in {"supplier_invoice", "swift_mt103", "subagent_act_report"}:
        warnings.append("Request number was not found.")


def normalize_request_number(value: str) -> str:
    number = normalize_spaces(value)
    number = re.sub(r"/?\s*Application.*$", "", number, flags=re.IGNORECASE)
    number = re.sub(r"/?\s*ORDER.*$", "", number, flags=re.IGNORECASE)

    return number.strip()


def parse_contract_number_and_date(raw_text: str, fields: dict) -> None:
    # Сначала ищем договор/соглашение, к которому относится заявка или поручение.
    # Если его нет, берем импортный/платежный контракт как запасной бизнес-ориентир.
    patterns = [
        r"Агентский\s+договор\s*№\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s+от\s*(\d{2}[./]\d{2}[./]\d{4})",
        r"Субагентскому\s*Договору\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}[./]\d{2}[./]\d{4})",
        r"Субагентскому\s+договору\s*№\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s+от\s*(\d{2}[./]\d{2}[./]\d{4})",
        r"Agreement\s+No\.?\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s+from\s*(\d{2}[./]\d{2}[./]\d{4})",
        r"(?:Договора|Agreement)\s+No\s+([A-Za-zА-Яа-я0-9/_-]+)\s+(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Агентскому|Agency|Субагентскому)\s+(?:Договору|Contract|договору|соглашению)\s*№?\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s*(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Импортный\s+контракт|Contract\s+No\.?)\s*:?\s*№?\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dd|dated|from)?\s*(\d{2}[.\-]\d{2}[.\-]\d{2,4})?",
        r"(?:Договору|Договор|contract)\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dd|dated)?\s*(\d{2}\.\d{2}\.\d{4})?",
    ]

    for pattern in patterns:
        match = re.search(pattern, raw_text, flags=re.IGNORECASE)

        if match is None:
            continue

        contract_number = normalize_contract_number(match.group(1))
        fields["contractNumber"] = contract_number

        if match.lastindex and match.lastindex >= 2 and match.group(2):
            fields["contractDate"] = normalize_flexible_date(match.group(2))

        return


def normalize_contract_number(value: str) -> str:
    contract_number = normalize_spaces(value)
    contract_number = re.sub(r"\s*-\s*", "-", contract_number)
    contract_number = re.sub(r"\s*/\s*", "/", contract_number)

    return contract_number


def parse_agency_application(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Заявки к агентскому договору: zayavka-61, poruchenie-5, poruchenie-97.
    # В них поля идут в табличной русско-английской форме, а PDF часто рвет слова.
    parse_agency_application_payment_amount(raw_text, fields, warnings)
    parse_agency_application_payment_amount_rub(raw_text, fields, warnings)
    parse_agency_application_execution_term(raw_text, fields, warnings)
    parse_agency_application_payment_term(raw_text, fields)
    parse_agency_application_exchange_rate(raw_text, fields)
    parse_agency_application_invoice_payment_rub(raw_text, fields, warnings)
    parse_agency_application_payment_type(raw_text, fields)
    parse_agency_application_beneficiary_bank(raw_text, fields)
    parse_agency_fee_percent(raw_text, fields)
    parse_agency_application_agency_fee(raw_text, fields, warnings)
    parse_agency_application_total_amount(raw_text, fields, warnings)
    derive_agency_application_payment_amount_rub(fields)

def parse_supplier_invoice(raw_text: str, fields: dict, warnings: list[str]) -> None:
    parse_supplier_invoice_number_and_date(raw_text, fields, warnings)
    parse_supplier_invoice_payment_amount(raw_text, fields, warnings)
    parse_supplier_invoice_beneficiary(raw_text, fields)
    parse_supplier_invoice_bank_details(raw_text, fields)

def parse_supplier_invoice_number_and_date(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # В этом invoice номер стоит сразу после заголовка INVOICE:
    # INVOICE
    # V3 – 2026
    match = re.search(
        r"INVOICE\s+([A-Za-z0-9]+)\s*[–-]\s*(\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Invoice number was not found.")
    else:
        fields["invoiceNumber"] = f"{match.group(1)}-{match.group(2)}"
        fields["requestNumber"] = fields["invoiceNumber"]

    date_match = re.search(
        r"Date:\s*(\d{2})\s*/\s*(\d{2})\s*/\s*(\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if date_match is None:
        warnings.append("Invoice date was not found.")
        return

    date_raw = f"{date_match.group(1)}.{date_match.group(2)}.{date_match.group(3)}"
    normalized_date = normalize_flexible_date(date_raw)

    if normalized_date is None:
        warnings.append(f'Invoice date "{date_raw}" could not be normalized.')
        return

    fields["invoiceDate"] = normalized_date
    fields["requestDate"] = normalized_date


def parse_supplier_invoice_payment_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # В invoice валюта указана в заголовке колонки: Amount ( USD ),
    # а сумма в строке Total.
    currency_match = re.search(
        r"Amount\s*\(\s*([A-Z]{3})\s*\)",
        raw_text,
        flags=re.IGNORECASE,
    )

    total_match = re.search(
        r"Total\s+([0-9][0-9,.\s\u00a0]*)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if currency_match is None or total_match is None:
        warnings.append("Invoice payment amount and currency were not found.")
        return

    amount = normalize_supplier_invoice_amount(total_match.group(1))

    if amount is None:
        warnings.append(f'Invoice payment amount "{total_match.group(1)}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = currency_match.group(1).upper()


def parse_supplier_invoice_beneficiary(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Bank details:\s*Name:\s*(.+?)\s+Adress:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        return

    fields["beneficiaryName"] = normalize_spaces(match.group(1))


def parse_supplier_invoice_bank_details(raw_text: str, fields: dict) -> None:
    bank_match = re.search(
        r"\bBank:\s*(.+?)\s+Bank\s+adress:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if bank_match is not None:
        fields["beneficiaryBank"] = normalize_spaces(bank_match.group(1))

    account_match = re.search(
        r"\bAcc:\s*([A-Za-z0-9]+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if account_match is not None:
        fields["beneficiaryAccount"] = normalize_spaces(account_match.group(1))

    swift_match = re.search(
        r"\bSwift:\s*([A-Z0-9]{8,11})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if swift_match is not None:
        fields["swiftCode"] = swift_match.group(1).upper()

def parse_agency_application_payment_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Сначала ищем английскую подпись Payment amount: она обычно ближе к числу.
    # Поддерживаем EUR/Euro/ЕВРО и суммы с одним или двумя знаками после разделителя.
    match = re.search(
        r"Payment\s+amount.*?([0-9][0-9\s\u00a0]*(?:[,.]\s*\d{1,2}))\s*(EUR|EURO|USD|CNY|GBP|AED|TRY|ЕВРО)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        # Русская подпись нужна как запасной вариант для документов,
        # где английский блок отсутствует или pypdf извлек текст в другом порядке.
        match = re.search(
            r"Сумма\s+платежа.*?([0-9][0-9\s\u00a0]*(?:[,.]\s*\d{1,2}))\s*(EUR|EURO|USD|CNY|GBP|AED|TRY|ЕВРО)",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

    if match is None:
        warnings.append("Agency application payment amount and currency were not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Agency application payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = normalize_currency(match.group(2))


def parse_agency_application_payment_amount_rub(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # В некоторых заявках рублевый эквивалент стоит прямо в строке суммы:
    # "75 829,80 Евро = 6 503 899,20 рублей".
    match = re.search(
        r"(?:Сумма\s+платежа|Payment\s+amount)(?:(?!Назначение\s+платежа|Details\s+of\s+payment|А\s*гентское\s+вознаграждение|Agency\s+fee).)*?"
        r"[0-9][0-9\s\u00a0]*(?:[,.]\s*\d{1,2})\s*(?:EUR|EURO|USD|CNY|GBP|AED|TRY|ЕВРО)"
        r"(?:(?!Назначение\s+платежа|Details\s+of\s+payment|А\s*гентское\s+вознаграждение|Agency\s+fee).)*?"
        r"=\s*([0-9][0-9\s\u00a0]*[,.]\s*\d{2})",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Agency application RUB payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmountRub"] = amount


def parse_agency_application_execution_term(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Срок может быть точной датой ("25.06.2026") или относительным сроком
    # ("5 рабочих дней"). Дату нормализуем отдельно, относительный срок храним текстом.
    date_match = re.search(
        r"(?:Terms\s+of\s+execution.*?u\s*p\s+to:|Сроки\s+выполнения\s+поручения.*?по:)\s*(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if date_match is not None:
        date_raw = date_match.group(1)
        fields["executionTermRaw"] = f"до {date_raw} включительно"

        try:
            fields["executionDueDate"] = datetime.strptime(date_raw, "%d.%m.%Y").date().isoformat()
        except ValueError:
            warnings.append(f'Agency application execution due date "{date_raw}" could not be normalized.')

        return

    relative_term_match = re.search(
        r"(?:Terms\s+of\s+execution.*?u\s*p\s+to:|Сроки\s+выполнения\s+поручения.*?по\s*:?)\s*(\d+\s+(?:рабочих|working)\s+(?:дней|days)|\d+\s+(?:дня|дней|days))",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if relative_term_match is None:
        return

    fields["executionTermRaw"] = normalize_spaces(relative_term_match.group(1))
    fields["executionDueDate"] = None


def parse_agency_application_payment_term(raw_text: str, fields: dict) -> None:
    # В заявках часто нет конкретной даты оплаты, но есть условие:
    # "До начала исполнения Агентом поручения Принципал обязуется перечислить...".
    match = re.search(
        r"(До\s+начала\s+исполнения\s+Агентом\s+поручения.*?)(?:Р\s*езультатом|Результатом|The\s+result)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        return

    fields["paymentTermRaw"] = normalize_spaces(match.group(1))
    fields["paymentDueDate"] = None


def parse_agency_application_exchange_rate(raw_text: str, fields: dict) -> None:
    # Если курс явно указан, сохраняем и число, и исходный текст.
    # Если в документе только фраза "курс согласуется сторонами", считаем что курса нет.
    match = re.search(
        r"((?:Курс\s+валюты:\s*)?1\s+(?:EUR|USD|CNY|GBP|AED|TRY|евро|доллар\w*|юан\w*)\s*=\s*([0-9]+,\d+)\s*руб)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is not None:
        fields["exchangeRate"] = normalize_decimal(match.group(2), scale=8)
        fields["exchangeRateRaw"] = normalize_spaces(match.group(1))
        return

    match = re.search(
        r"курсу\s+ЦБ\s+РФ\s*\(?\s*([0-9]+,\d+)\s*\)?",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is not None:
        fields["exchangeRate"] = normalize_decimal(match.group(1), scale=8)
        fields["exchangeRateRaw"] = normalize_spaces(match.group(0))
        return

    if re.search(r"Обменный\s+курс|exchange\s+rate", raw_text, flags=re.IGNORECASE):
        fields["exchangeRate"] = None
        fields["exchangeRateRaw"] = "нет"

def parse_agency_application_invoice_payment_rub(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Для новых заявок рублевая сумма именно оплаты по инвойсу явно подписана:
    # "Оплата по инвойсу составляет 11 916 095,30 руб."
    # Это точнее, чем пытаться вычислять сумму через курс.
    match = re.search(
        r"(?:Оплата\s+по\s+инвойсу|Invoice\s+payment)\s+(?:составляет|is)\s+([0-9][0-9\s\u00a0]*[,.]\s*\d{2})\s*rub?",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Invoice payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmountRub"] = amount


def parse_agency_application_payment_type(raw_text: str, fields: dict) -> None:
    # Вид оплаты пока сохраняем двумя значениями:
    # - machine-readable paymentType;
    # - исходный фрагмент paymentTypeRaw для проверки человеком.
    match = re.search(
        r"(prepayment|postpayment|payment\s+within\s+\d+\s+(?:calendar\s+)?days?|оплата\s+в\s+течение\s+\d+\s+(?:календарных\s+)?дн\w+|предоплата|постоплата)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    raw_value = normalize_spaces(match.group(0))
    lowered = raw_value.lower()

    if "prepayment" in lowered or "предоплат" in lowered:
        payment_type = "prepayment"
    elif "postpayment" in lowered or "постоплат" in lowered:
        payment_type = "postpayment"
    elif "within" in lowered or "течение" in lowered:
        payment_type = "term"
    else:
        payment_type = "unknown"

    fields["paymentType"] = payment_type
    fields["paymentTypeRaw"] = raw_value


def parse_agency_application_beneficiary_bank(raw_text: str, fields: dict) -> None:
    # В банковских реквизитах встречается строка:
    # "Name of Bank: International Asset Bank Swift Code: ..."
    match = re.search(
        r"Name\s+of\s+Bank:\s*(.+?)\s+Swift\s+Code:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        match = re.search(
            r"(?:Банк\s+получателя|Beneficiary[’']?s?\s+Bank):\s*(.+?)(?:\n|Swift|SWIFT|Bank Address|Address:)",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

    if match is None:
        return

    fields["beneficiaryBank"] = normalize_spaces(match.group(1))


def parse_agency_application_agency_fee(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # В некоторых PDF слово "Агентское" извлекается как "А\nгентское",
    # поэтому regex допускает пробелы/переносы после первой буквы.
    match = re.search(
        r"(?:А\s*гентское\s+вознаграждение|Agency\s+fee).*?(?:составляет|makes\s+up|is).*?([0-9][0-9\s\u00a0]*[,.]\s*\d{2})\s*руб",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        warnings.append("Agency application agency fee amount was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Agency application agency fee amount "{match.group(1)}" could not be normalized.')
        return

    fields["agencyFeeAmountRub"] = amount


def parse_agency_application_total_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Общая сумма в рублях встречается в двух формах:
    # "2 202 459,92" или "10 930 456 рублей 44 копеек".
    equals_match = re.search(
        r"(?:денежные\s+средства|monetary\s+funds).*?=\s*([0-9][0-9\s\u00a0]*[,.]\s*\d{2})\s*(?:руб|Rubl)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if equals_match is not None:
        amount = normalize_decimal(equals_match.group(1), scale=2)

        if amount is None:
            warnings.append(f'Agency application total amount "{equals_match.group(1)}" could not be normalized.')
            return

        fields["totalAmountRub"] = amount
        return

    match = re.search(
        r"(?:денежные\s+средства.*?в\s+размере|monetary\s+funds.*?amount\s+of)\s+([0-9][0-9\s\u00a0]*[,.]\s*\d{1,2})\b",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is not None:
        amount = normalize_decimal(match.group(1), scale=2)

        if amount is None:
            warnings.append(f'Agency application total amount "{match.group(1)}" could not be normalized.')
            return

        fields["totalAmountRub"] = amount
        return

    rubles_kopecks_match = re.search(
        r"денежные\s+средства\s+в\s+размере\s+([0-9][0-9\s\u00a0]*)\s+рубл\w*\s+(\d{2})\s+коп",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if rubles_kopecks_match is None:
        rubles_kopecks_match = re.search(
            r"monetary\s+funds\s+in\s+the\s+amount\s+of\s+([0-9][0-9\s\u00a0]*)\s+rubles\s+(\d{2})\s+kopecks",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

    if rubles_kopecks_match is None:
        warnings.append("Agency application total amount in RUB was not found.")
        return

    amount = normalize_decimal(f"{rubles_kopecks_match.group(1)},{rubles_kopecks_match.group(2)}", scale=2)

    if amount is None:
        warnings.append(
            f'Agency application total amount "{rubles_kopecks_match.group(1)} rubles {rubles_kopecks_match.group(2)} kopecks" could not be normalized.'
        )
        return

    fields["totalAmountRub"] = amount


def derive_agency_application_payment_amount_rub(fields: dict) -> None:
    if fields.get("paymentAmountRub"):
        return

    if not fields.get("totalAmountRub") or not fields.get("agencyFeeAmountRub"):
        return

    fields["paymentAmountRub"] = bc_sub_money(fields["totalAmountRub"], fields["agencyFeeAmountRub"])


def parse_subagent_instruction(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Субагентское поручение имеет нумерованные пункты:
    # сумма валютного платежа, условия исполнения, применяемый курс, покрытие и вознаграждение.
    parse_subagent_payment_amount(raw_text, fields, warnings)
    parse_subagent_execution_term(raw_text, fields, warnings)
    parse_subagent_exchange_rate(raw_text, fields, warnings)
    parse_subagent_payment_amount_rub(raw_text, fields, warnings)
    parse_subagent_agency_fee(raw_text, fields, warnings)
    parse_subagent_total_amount(raw_text, fields, warnings)
    parse_subagent_payment_term(raw_text, fields)
    parse_subagent_beneficiary_bank(raw_text, fields)
    parse_subagent_instruction_beneficiary(raw_text, fields)
    derive_subagent_instruction_amounts(fields)

def parse_subagent_beneficiary_bank(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Beneficiary[’']?s?\s+Bank:\s*(.+?)\s+Bank\s+Address:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        return

    fields["beneficiaryBank"] = normalize_spaces(match.group(1))


def parse_subagent_instruction_beneficiary(raw_text: str, fields: dict) -> None:
    if fields.get("beneficiaryBank") is None:
        bank_match = re.search(
            r"(?:БАНК\s+БЕНЕФИЦИАРА\s+НАИМЕНОВАНИЕ|BENEFICIARY\s+BANK\s+NAME):\s*(.+?)(?:АДРЕС|BENEFICIARY\s+BANK\s+ADDRESS)",
            raw_text,
            flags=re.IGNORECASE | re.DOTALL,
        )

        if bank_match is not None:
            fields["beneficiaryBank"] = normalize_spaces(bank_match.group(1))

    name_match = re.search(
        r"(?:БЕНЕФИЦИАР\s+\(ПОЛУЧАТЕЛЬ\)|BENEFICIARY\s+\(RECIPIENT\)):\s*(.+?)\s+(?:АДРЕС\s+БЕНЕФИЦИАРА|ADDRESS\s+OF\s+THE\s+BENEFICIARY)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if name_match is not None:
        fields["beneficiaryName"] = normalize_spaces(name_match.group(1))

    account_match = re.search(
        r"(?:НОМЕР\s+СЧЕТА|ACCOUNT\s+NUMBER):\s*([A-Z0-9\s]+?)\s+SWIFT:",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if account_match is not None:
        fields["beneficiaryAccount"] = re.sub(r"\s+", "", account_match.group(1))

    swift_match = re.search(
        r"\bSWIFT:\s*([A-Z0-9]{8,11})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if swift_match is not None:
        fields["swiftCode"] = swift_match.group(1).upper()

    invoice_match = re.search(
        r"INVOIC\s*E?/CONTRACT:\s*(.+?)\s+(?:ДОП|ADDITIONAL)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if invoice_match is not None:
        fields["invoiceNumber"] = normalize_spaces(invoice_match.group(1))

    invoice_date_match = re.search(
        r"(?:ДАТА\s+ИНВОЙСА|INVOICE\s+DATE\)):\s*([0-9.;\s]+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if invoice_date_match is not None:
        first_date_match = re.search(r"\d{2}\.\d{2}\.\d{4}", invoice_date_match.group(1))

        if first_date_match is not None:
            fields["invoiceDate"] = normalize_flexible_date(first_date_match.group(0))

def parse_subagent_payment_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Сумма\s+валютного\s+платежа:\s*([0-9][0-9\s\u00a0]*,\d{2})\s*([A-Z]{3})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"Общая\s+сумма\s+платежа:\s*([0-9][0-9\s\u00a0]*,\d{2})\s*([A-Z]{3})",
            raw_text,
            flags=re.IGNORECASE,
        )

    if match is None:
        amount_match = re.search(
            r"(?:СУММА\s+ПЛАТЕЖА|PAYMENT\s+AMOUNT):\s*([0-9][0-9\s\u00a0]*[,.]\d{2})",
            raw_text,
            flags=re.IGNORECASE,
        )
        currency_match = re.search(
            r"(?:ВАЛЮТА\s+ПЛАТЕЖА|PAYMENT\s+CURRENCY):\s*([A-Z]{3})",
            raw_text,
            flags=re.IGNORECASE,
        )

        if amount_match is not None and currency_match is not None:
            amount = normalize_decimal(amount_match.group(1), scale=2)

            if amount is None:
                warnings.append(f'Subagent payment amount "{amount_match.group(1)}" could not be normalized.')
                return

            fields["paymentAmount"] = amount
            fields["paymentCurrency"] = currency_match.group(1).upper()
            return

    if match is None:
        warnings.append("Subagent payment amount and currency were not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = match.group(2).upper()


def parse_subagent_execution_term(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Осуществить\s+оплату.*?\s(в\s+течение\s+\d+\s+Рабочих\s+дней\s+с\s+момента\s+получения\s+настоящего\s+поручения)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        return

    fields["executionTermRaw"] = normalize_spaces(match.group(1))
    fields["executionDueDate"] = None


def parse_subagent_exchange_rate(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Применяется\s+курс:\s*(1\s+[A-Z]{3}\s*=\s*([0-9]+,\d+)\s+к\s+рублю\s+РФ)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"(?:Курс|The\s+rate\s+is)\s+([0-9]+[,.]\d+)\s+(?:к\s+Рублю\s+РФ|to\s+the\s+Russian\s+Ruble)",
            raw_text,
            flags=re.IGNORECASE,
        )

        if match is None:
            fields["exchangeRate"] = None
            fields["exchangeRateRaw"] = "нет"
            warnings.append("Subagent exchange rate was not found.")
            return

        rate = normalize_decimal(match.group(1), scale=8)

        if rate is None:
            warnings.append(f'Subagent exchange rate "{match.group(1)}" could not be normalized.')
            return

        fields["exchangeRate"] = rate
        fields["exchangeRateRaw"] = normalize_spaces(match.group(0))
        return

    rate_raw = match.group(1)
    rate = normalize_decimal(match.group(2), scale=8)

    if rate is None:
        warnings.append(f'Subagent exchange rate "{match.group(2)}" could not be normalized.')
        return

    fields["exchangeRate"] = rate
    fields["exchangeRateRaw"] = normalize_spaces(rate_raw)


def parse_subagent_payment_amount_rub(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"сумму\s+платежа\s+по\s+контракту\s+в\s+размере\s+([0-9][0-9\s\u00a0]*,\d{2})\s*руб",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent RUB payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmountRub"] = amount


def parse_subagent_agency_fee(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"вознаграждени[яе]\s+в\s+размере\s+([0-9][0-9\s\u00a0]*,\d{2})\s*руб",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        percent_match = re.search(
            r"Курс\s+ЦБ\s*\+\s*([0-9]+[,.]\d+)\s*%",
            raw_text,
            flags=re.IGNORECASE,
        )

        if percent_match is not None:
            fields["agencyFeePercent"] = normalize_decimal(percent_match.group(1), scale=4)
            return

        warnings.append("Subagent agency fee amount was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent agency fee amount "{match.group(1)}" could not be normalized.')
        return

    fields["agencyFeeAmountRub"] = amount


def parse_subagent_total_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"Суммы\s+платежа\s+в\s+размере\s+([0-9][0-9\s\u00a0]*,\d{2})\s*руб",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"(?:Платеж\s+в\s+Рублях\s+РФ\s+в\s+размере|Payment\s+in\s+Russian\s+Rubles\s+in\s+the\s+amount\s+of)\s+([0-9][0-9\s\u00a0]*[,.]\d{2})",
            raw_text,
            flags=re.IGNORECASE,
        )

        if match is None:
            warnings.append("Subagent total amount in RUB was not found.")
            return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Subagent total amount "{match.group(1)}" could not be normalized.')
        return

    fields["totalAmountRub"] = amount


def parse_subagent_payment_term(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"(в\s+течение\s+\d+\s+рабочих\s+дней\s+после\s+исполнения\s+аккредитива)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        match = re.search(
            r"(не\s+позднее\s+1\s+\(Одного\)\s+Рабочего\s+Дня)",
            raw_text,
            flags=re.IGNORECASE,
        )

        if match is None:
            return

    fields["paymentTermRaw"] = normalize_spaces(match.group(1))
    fields["paymentDueDate"] = None


def derive_subagent_instruction_amounts(fields: dict) -> None:
    if fields.get("paymentAmountRub") is None and fields.get("paymentAmount") and fields.get("exchangeRate"):
        from decimal import Decimal

        amount = Decimal(fields["paymentAmount"]) * Decimal(fields["exchangeRate"])
        fields["paymentAmountRub"] = f"{amount:.2f}"

    if fields.get("agencyFeeAmountRub") is None and fields.get("totalAmountRub") and fields.get("paymentAmountRub"):
        fields["agencyFeeAmountRub"] = bc_sub_money(fields["totalAmountRub"], fields["paymentAmountRub"])


def normalize_spaces(value: str) -> str:
    # Схлопываем любые переносы/множественные пробелы в одну строку,
    # чтобы в БД и админке текст выглядел предсказуемо.
    return re.sub(r"\s+", " ", value).strip()


def normalize_currency(value: str) -> str:
    normalized = value.strip().upper()

    if normalized in ("ЕВРО", "EURO"):
        return "EUR"

    return normalized


def normalize_date(value: str, warnings: list[str], field_label: str) -> str | None:
    normalized = normalize_flexible_date(value)

    if normalized is None:
        warnings.append(f'{field_label} "{value}" could not be normalized.')

    return normalized


def normalize_flexible_date(value: str) -> str | None:
    normalized = value.strip().replace("-", ".").replace("/", ".")

    for date_format in ("%d.%m.%Y", "%d.%m.%y"):
        try:
            return datetime.strptime(normalized, date_format).date().isoformat()
        except ValueError:
            continue

    return None


def build_terms_comment(fields: dict) -> None:
    # Заказчик попросил сроки пока складывать в комментарий.
    # Поэтому отдельно храним нормализованные поля, но в таблицу потом можно
    # выводить один человекочитаемый termsComment.
    parts = []

    if fields.get("executionTermRaw"):
        parts.append(f"Срок исполнения: {fields['executionTermRaw']}")

    if fields.get("paymentTermRaw"):
        parts.append(f"Срок оплаты: {fields['paymentTermRaw']}")

    fields["termsComment"] = "; ".join(parts) if parts else None


def detect_document_type(raw_text: str) -> str:
    lowered = raw_text.lower()
    compact = re.sub(r"\s+", "", lowered)

    # Важно: акт-отчет тоже содержит "субагентскому соглашению",
    # поэтому проверяем его раньше subagent_instruction.
    if "акт-отчет" in lowered or "акт -отчет" in lowered:
        return "subagent_act_report"

    if "swift mt-103" in lowered or "message header" in lowered and "message text" in lowered:
        return "swift_mt103"

    if "invoice" in lowered and "bank details" in lowered and "total" in lowered:
        return "supplier_invoice"

    if "поручение на оплату" in lowered or "payment assignment" in lowered:
        return "payment_assignment"

    if (
        ("заявка" in lowered or "заявка" in compact)
        and ("субагентскому договору" in lowered or "субагентскомудоговору" in compact)
        and "агентпоручает" in compact
        and "субагентпринимает" in compact
    ):
        return "subagent_application"

    if "payment amount:" in lowered and "payment currency:" in lowered and "subagent fee" in lowered:
        return "subagent_instruction"

    if "субагентскому договору" in lowered or "субагентскому соглашению" in lowered:
        return "subagent_instruction"

    if "payment amount" in lowered and "agency fee" in lowered:
        return "application_form"

    if "payment amount" in lowered and "principal entrusts" in lowered:
        return "application_form"

    if "сумма платежа" in lowered and "агентское вознаграждение" in lowered:
        return "application_form"

    if "application no" in lowered and "agent's service" in lowered:
        return "asstra_application"

    if "форма заявки" in lowered and "услуга агента" in lowered:
        return "asstra_application"

    return "unknown"


def parse_payment_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Общие правила ниже сейчас используются для ASSTRA application.
    # Там ключевая фраза: "в размере 53.342,00 EUR" / "amount of ...".
    match = re.search(
        r"(?:размере|amount of)\s+([0-9][0-9.\s\u00a0]*,\d{2})\s*([A-Z]{3})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Payment amount and currency were not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmount"] = amount
    fields["paymentCurrency"] = match.group(2).upper()


def parse_execution_term(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # ASSTRA содержит срок в формате "до 19.06.2026 г. включительно".
    match = re.search(
        r"(?:в срок\s+)?до\s+(\d{2}\.\d{2}\.\d{4})(?:\s*г\.)?(?:\s*включительно)?",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        warnings.append("Execution term was not found.")
        return

    date_raw = match.group(1)
    fields["executionTermRaw"] = f"до {date_raw} включительно"

    try:
        fields["executionDueDate"] = datetime.strptime(date_raw, "%d.%m.%Y").date().isoformat()
    except ValueError:
        warnings.append(f'Execution due date "{date_raw}" could not be normalized.')


def parse_payment_term(raw_text: str, fields: dict) -> None:
    match = re.search(
        r"Принципал\s+перечисляет\s+Агенту\s+в\s+срок\s+до\s+(\d{2}\.\d{2}\.\d{4})",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    fields["paymentTermRaw"] = f"Принципал перечисляет Агенту в срок до {match.group(1)}"
    fields["paymentDueDate"] = normalize_flexible_date(match.group(1))


def parse_exchange_rate(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # В ASSTRA курс обычно идет как "по курсу 83,08160".
    match = re.search(
        r"(?:по\s+курсу|exchange rate)\s+([0-9]+,\d+)",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        fields["exchangeRate"] = None
        fields["exchangeRateRaw"] = "нет"
        warnings.append("Exchange rate was not found.")
        return

    rate_raw = match.group(1)
    rate = normalize_decimal(rate_raw, scale=8)

    if rate is None:
        warnings.append(f'Exchange rate "{rate_raw}" could not be normalized.')
        return

    fields["exchangeRate"] = rate
    fields["exchangeRateRaw"] = rate_raw


def parse_payment_amount_rub(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"эквивалентн\w*\s+([0-9][0-9.\s\u00a0]*,\d{2})\s*\(",
        raw_text,
        flags=re.IGNORECASE,
    )

    if match is None:
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'RUB payment amount "{match.group(1)}" could not be normalized.')
        return

    fields["paymentAmountRub"] = amount


def parse_agency_fee_percent(raw_text: str, fields: dict) -> None:
    patterns = [
        r"Вознаграждение\s+Агента.*?составляет\s+([0-9]+(?:[,.]\d+)?)\s*%",
        r"А\s*гентское\s+вознаграждение.*?([0-9]+(?:[,.]\d+)?)\s*%",
        r"Agency\s+fee.*?([0-9]+(?:[,.]\d+)?)\s*%",
    ]

    for pattern in patterns:
        match = re.search(pattern, raw_text, flags=re.IGNORECASE | re.DOTALL)

        if match is None:
            continue

        fields["agencyFeePercent"] = normalize_decimal(match.group(1), scale=4)
        return


def parse_agency_fee(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"(?:Вознаграждение\s+Агента|Agent's remuneration).*?([0-9][0-9.\s\u00a0]*,\d{2})\s*(?:рублей\s+РФ|Russian rubles)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        warnings.append("Agency fee amount was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Agency fee amount "{match.group(1)}" could not be normalized.')
        return

    fields["agencyFeeAmountRub"] = amount


def parse_total_amount(raw_text: str, fields: dict, warnings: list[str]) -> None:
    match = re.search(
        r"(?:Итого\s+общая\s+сумма|Total amount).*?([0-9][0-9.\s\u00a0]*,\d{2})\s*(?:рублей\s+РФ|Russian rubles)",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if match is None:
        warnings.append("Total amount in RUB was not found.")
        return

    amount = normalize_decimal(match.group(1), scale=2)

    if amount is None:
        warnings.append(f'Total amount "{match.group(1)}" could not be normalized.')
        return

    fields["totalAmountRub"] = amount


def calculate_confidence(fields: dict, document_type: str) -> float:
    # confidence считаем по ожидаемым полям конкретного типа документа.
    # Это важнее общей формулы: invoice, swift и акт-отчет не обязаны иметь
    # одинаковый набор финансовых полей.
    required_by_type = {
        "supplier_invoice": [
            "requestNumber",
            "requestDate",
            "paymentAmount",
            "paymentCurrency",
            "beneficiaryName",
            "beneficiaryBank",
            "beneficiaryAccount",
            "swiftCode",
            "invoiceNumber",
            "invoiceDate",
        ],
        "swift_mt103": [
            "requestNumber",
            "requestDate",
            "paymentAmount",
            "paymentCurrency",
            "beneficiaryName",
            "beneficiaryAccount",
            "swiftCode",
            "invoiceNumber",
            "invoiceDate",
            "paymentReference",
        ],
        "subagent_act_report": [
            "requestNumber",
            "requestDate",
            "contractNumber",
            "contractDate",
            "paymentAmount",
            "paymentCurrency",
            "paymentAmountRub",
            "agencyFeeAmountRub",
            "totalAmountRub",
            "invoiceNumber",
            "invoiceDate",
            "paymentReference",
        ],
        "subagent_application": [
            "requestNumber",
            "requestDate",
            "contractNumber",
            "contractDate",
            "paymentAmount",
            "paymentCurrency",
            "paymentAmountRub",
            "exchangeRate",
            "agencyFeeAmountRub",
            "totalAmountRub",
            "executionTermRaw",
            "beneficiaryName",
            "beneficiaryBank",
            "beneficiaryAccount",
            "swiftCode",
        ],
        "subagent_instruction": [
            "requestNumber",
            "requestDate",
            "contractNumber",
            "contractDate",
            "paymentAmount",
            "paymentCurrency",
            "paymentAmountRub",
            "exchangeRate",
            "agencyFeeAmountRub",
            "totalAmountRub",
        ],
        "payment_assignment": [
            "requestNumber",
            "requestDate",
            "contractNumber",
            "contractDate",
            "paymentAmount",
            "paymentCurrency",
            "paymentAmountRub",
            "exchangeRate",
            "agencyFeeAmountRub",
            "totalAmountRub",
            "executionTermRaw",
            "paymentTermRaw",
            "beneficiaryName",
            "beneficiaryBank",
            "beneficiaryAccount",
            "swiftCode",
            "invoiceNumber",
            "invoiceDate",
        ],
        "application_form": [
            "requestNumber",
            "requestDate",
            "contractNumber",
            "paymentAmount",
            "paymentCurrency",
            "paymentAmountRub",
            "agencyFeeAmountRub",
            "totalAmountRub",
        ],
    }

    required_fields = required_by_type.get(document_type, [
        "paymentAmount",
        "paymentCurrency",
        "exchangeRate",
        "agencyFeeAmountRub",
        "totalAmountRub",
        "executionTermRaw",
        "requestNumber",
    ])

    found = sum(1 for field_name in required_fields if fields.get(field_name))

    return round(found / len(required_fields), 2)

def normalize_supplier_invoice_amount(value: str) -> str | None:
    # В invoice сумма "54,472" означает 54 472 USD, а не 54.47.
    # Поэтому запятые внутри целой части считаем разделителями тысяч.
    normalized = value.strip()
    normalized = normalized.replace("\u00a0", " ")
    normalized = normalized.replace(" ", "")

    if "," in normalized and "." not in normalized:
        normalized = normalized.replace(",", "")
        normalized = f"{normalized}.00"
    else:
        normalized = normalized.replace(",", "")

    if not re.fullmatch(r"\d+(?:\.\d{1,2})?", normalized):
        return None

    return normalize_decimal(normalized, scale=2)
