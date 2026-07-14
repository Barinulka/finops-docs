<?php

namespace App\Enum\Telegram;

enum TelegramMessageDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Входящее',
            self::Outgoing => 'Исходящее',
        };
    }
}
