<?php

namespace App\Enum\Telegram;

enum TelegramMessageStatus: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Failed = 'failed';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получено',
            self::Sent => 'Отправлено',
            self::Failed => 'Ошибка',
            self::Ignored => 'Проигнорировано',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Received => 'text-bg-info',
            self::Sent => 'text-bg-success',
            self::Failed => 'text-bg-danger',
            self::Ignored => 'text-bg-secondary',
        };
    }
}
