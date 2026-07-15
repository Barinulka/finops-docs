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
        "termsComment": None,
    }
    warnings: list[str] = []

    document_type = detect_document_type(raw_text)
    parse_common_request_metadata(raw_text, fields, warnings)

    # У каждого шаблона PDF свои формулировки, поэтому держим отдельные ветки.
    # Общая ветка в else сейчас покрывает ASSTRA-заявки.
    if document_type == "subagent_instruction":
        parse_subagent_instruction(raw_text, fields, warnings)
    elif document_type == "application_form":
        parse_agency_application(raw_text, fields, warnings)
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

    confidence = calculate_confidence(fields)

    return fields, warnings, document_type, confidence


def parse_common_request_metadata(raw_text: str, fields: dict, warnings: list[str]) -> None:
    # Номер и дата заявки/поручения встречаются почти во всех шаблонах.
    # Делаем это общей функцией, чтобы не дублировать одинаковые regex в ветках.
    parse_request_number_and_date(raw_text, fields, warnings)
    parse_contract_number_and_date(raw_text, fields)


def parse_request_number_and_date(raw_text: str, fields: dict, warnings: list[str]) -> None:
    patterns = [
        r"(?:Заявка|Application)\s*№?\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Поручение)\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*от\s*(\d{2}\.\d{2}\.\d{4})",
    ]

    for pattern in patterns:
        match = re.search(pattern, raw_text, flags=re.IGNORECASE)

        if match is None:
            continue

        fields["requestNumber"] = normalize_spaces(match.group(1))
        fields["requestDate"] = normalize_date(match.group(2), warnings, "Request date")
        return

    # У некоторых двуязычных заявок номер и дата лежат в разных местах:
    # "Заявка № 61 / Application No. 61" и ниже "Дата 25.06.2026".
    number_match = re.search(
        r"(?:Заявка|Application|Поручение)\s*№?\.?\s*([A-Za-zА-Яа-я0-9_-]+)",
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
        fields["requestNumber"] = normalize_spaces(number_match.group(1))

    if date_match is not None:
        fields["requestDate"] = normalize_date(date_match.group(1), warnings, "Request date")

    if fields["requestNumber"] is None:
        warnings.append("Request number was not found.")


def parse_contract_number_and_date(raw_text: str, fields: dict) -> None:
    # Сначала ищем договор/соглашение, к которому относится заявка или поручение.
    # Если его нет, берем импортный/платежный контракт как запасной бизнес-ориентир.
    patterns = [
        r"Агентский\s+договор\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s+от\s*(\d{2}[./]\d{2}[./]\d{4})",
        r"(?:Договора|Agreement)\s+No\s+([A-Za-zА-Яа-я0-9/_-]+)\s+(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Агентскому|Agency|Субагентскому)\s+(?:Договору|Contract|договору|соглашению)\s*№?\s*([A-Za-zА-Яа-я0-9/_\-\s]+?)\s*(?:от|dated)\s*(\d{2}\.\d{2}\.\d{4})",
        r"(?:Импортный\s+контракт|Contract\s+No\.?)\s*:?\s*№?\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dd|dated|from)?\s*(\d{2}[.\-]\d{2}[.\-]\d{2,4})?",
        r"(?:Договору|Договор|contract)\s*№\s*([A-Za-zА-Яа-я0-9/_-]+)\s*(?:от|dd|dated)?\s*(\d{2}\.\d{2}\.\d{4})?",
    ]

    for pattern in patterns:
        match = re.search(pattern, raw_text, flags=re.IGNORECASE)

        if match is None:
            continue

        contract_number = normalize_spaces(match.group(1))
        contract_number = re.sub(r"\s*-\s*", "-", contract_number)
        fields["contractNumber"] = contract_number

        if match.lastindex and match.lastindex >= 2 and match.group(2):
            fields["contractDate"] = normalize_flexible_date(match.group(2))

        return


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
        r"Payment\s+amount.*?[0-9][0-9\s\u00a0]*(?:[,.]\s*\d{1,2})\s*(?:EUR|EURO|USD|CNY|GBP|AED|TRY|ЕВРО).*?=\s*([0-9][0-9\s\u00a0]*[,.]\s*\d{2})",
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
        r"(?:Terms\s+of\s+execution.*?u\s*p\s+to:|Сроки\s+выполнения\s+поручения.*?по:)\s*(\d+\s+(?:рабочих|working)\s+(?:дней|days))",
        raw_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    if relative_term_match is None:
        warnings.append("Agency application execution term was not found.")
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
        warnings.append("Subagent execution term was not found.")
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
        fields["exchangeRate"] = None
        fields["exchangeRateRaw"] = "нет"
        warnings.append("Subagent exchange rate was not found.")
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
        return

    fields["paymentTermRaw"] = normalize_spaces(match.group(1))
    fields["paymentDueDate"] = None


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

    # Определяем тип документа по устойчивым фразам, а не по имени файла:
    # в production имя файла может быть любым.
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


def calculate_confidence(fields: dict) -> float:
    # confidence показывает долю найденных бизнес-полей.
    # Это не ML-уверенность, а простой технический индикатор полноты разбора.
    required_fields = [
        "requestNumber",
        "paymentAmount",
        "paymentCurrency",
        "exchangeRateRaw",
        "agencyFeeAmountRub",
        "paymentAmountRub",
        "executionTermRaw",
    ]

    found = 0

    for field_name in required_fields:
        value = fields.get(field_name)

        # paymentAmountRub бывает неявным: в части документов есть только итоговая
        # рублевая сумма. В таком случае totalAmountRub тоже считаем полезной находкой.
        if field_name == "paymentAmountRub" and value in (None, ""):
            value = fields.get("totalAmountRub")

        if value not in (None, ""):
            found += 1

    return round(found / len(required_fields), 2)
