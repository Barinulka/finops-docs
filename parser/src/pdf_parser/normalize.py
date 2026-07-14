from __future__ import annotations

import re
from decimal import Decimal, InvalidOperation


def normalize_decimal(value: str, scale: int) -> str | None:
    normalized = value.strip()

    # В PDF числа часто разрываются пробелами, неразрывными пробелами
    # и переносами строк: "10 930 \n456". Для Decimal все это нужно убрать.
    normalized = re.sub(r"[\s\u00a0]+", "", normalized)

    # Поддерживаем оба формата:
    # "53.342,00" -> европейский формат, точка разделяет тысячи, запятая дробь;
    # "127,680.00" -> англоязычный формат, запятая разделяет тысячи, точка дробь.
    # Дробным считаем тот разделитель, который встречается правее.
    if "," in normalized and "." in normalized:
        comma_position = normalized.rfind(",")
        dot_position = normalized.rfind(".")

        if comma_position > dot_position:
            normalized = normalized.replace(".", "")
            normalized = normalized.replace(",", ".")
        else:
            normalized = normalized.replace(",", "")
    elif "," in normalized:
        normalized = normalized.replace(",", ".")

    try:
        decimal = Decimal(normalized)
    except InvalidOperation:
        return None

    # Возвращаем строку, а не Decimal: так JSON стабилен и Symfony получает
    # уже готовое значение для decimal-полей без float-ошибок округления.
    quant = Decimal("1").scaleb(-scale)

    return str(decimal.quantize(quant))
