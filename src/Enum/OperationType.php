<?php

namespace App\Enum;

enum OperationType: string
{
    case Payment = 'payment';
    case Transfer = 'transfer';
    case CurrencyConversion = 'currency_conversion';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Платеж',
            self::Transfer => 'Перевод',
            self::CurrencyConversion => 'Конвертация валют',
            self::Other => 'Другое',
        };
    }
}
