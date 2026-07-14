<?php

namespace App\Enum\Telegram;

enum TelegramDocumentStatus: string
{
    case Received = 'received';
    case Duplicate = 'duplicate';
    case Queued = 'queued';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case Confirmed = 'confirmed';
    case Written = 'written';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получен',
            self::Duplicate => 'Дубликат',
            self::Queued => 'В очереди',
            self::Parsing => 'Обрабатывается',
            self::Parsed => 'Распарсен',
            self::NeedsReview => 'Требует проверки',
            self::Confirmed => 'Подтвержден',
            self::Written => 'Записан в таблицу',
            self::Failed => 'Ошибка',
            self::Cancelled => 'Отменен',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Received => 'text-bg-secondary',
            self::Duplicate => 'text-bg-dark',
            self::Queued => 'text-bg-info',
            self::Parsing => 'text-bg-primary',
            self::Parsed => 'text-bg-success',
            self::NeedsReview => 'text-bg-warning',
            self::Confirmed => 'text-bg-success',
            self::Written => 'text-bg-success',
            self::Failed => 'text-bg-danger',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
