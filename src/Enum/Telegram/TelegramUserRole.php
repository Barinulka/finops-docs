<?php

namespace App\Enum\Telegram;

enum TelegramUserRole: string
{
    case Operator = 'operator';
    case Manager = 'manager';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Оператор',
            self::Manager => 'Менеджер',
            self::Admin => 'Администратор',
        };
    }
}
