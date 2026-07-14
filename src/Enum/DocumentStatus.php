<?php

namespace App\Enum;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Загружен',
            self::Queued => 'В очереди',
            self::Parsing => 'Обрабатывается',
            self::Parsed => 'Распарсен',
            self::NeedsReview => 'Требует проверки',
            self::Confirmed => 'Подтвержден',
            self::Failed => 'Ошибка',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Uploaded => 'text-bg-secondary',
            self::Queued => 'text-bg-info',
            self::Parsing => 'text-bg-primary',
            self::Parsed => 'text-bg-success',
            self::NeedsReview => 'text-bg-warning',
            self::Confirmed => 'text-bg-success',
            self::Failed => 'text-bg-danger',
        };
    }
}
