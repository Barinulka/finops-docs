<?php

namespace App\Enum\Telegram;

enum GoogleSheetAppendStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает записи',
            self::Success => 'Записано',
            self::Failed => 'Ошибка',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-secondary',
            self::Success => 'text-bg-success',
            self::Failed => 'text-bg-danger',
        };
    }
}
