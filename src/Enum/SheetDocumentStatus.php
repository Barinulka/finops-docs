<?php

namespace App\Enum;

enum SheetDocumentStatus: string
{
    case Uploaded = 'uploaded';
    case QueuedForParsing = 'queued_for_parsing';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case ValidationFailed = 'validation_failed';
    case QueuedForWrite = 'queued_for_write';
    case Writing = 'writing';
    case Written = 'written';
    case Failed = 'failed';
    case WriteFailed = 'write_failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Загружен',
            self::QueuedForParsing => 'В очереди на парсинг',
            self::Parsing => 'Парсится',
            self::Parsed => 'Распарсен',
            self::NeedsReview => 'Требует проверки',
            self::ValidationFailed => 'Проверка не пройдена',
            self::QueuedForWrite => 'В очереди на запись',
            self::Writing => 'Записывается',
            self::Written => 'Записан в таблицу',
            self::Failed => 'Ошибка обработки',
            self::WriteFailed => 'Ошибка записи',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Uploaded,
            self::QueuedForParsing,
            self::QueuedForWrite => 'badge-secondary',

            self::Parsing,
            self::Writing => 'badge-info',

            self::Parsed => 'badge-success',

            self::NeedsReview,
            self::ValidationFailed => 'badge-warning',

            self::Written => 'badge-primary',

            self::Failed,
            self::WriteFailed => 'badge-danger',
        };
    }

    public function canBeParsed(): bool
    {
        return in_array($this, [
            self::Uploaded,
            self::Failed,
            self::NeedsReview,
            self::ValidationFailed,
            self::Parsed,
        ], true);
    }

    public function canBeQueuedForWrite(): bool
    {
        return in_array($this, [
            self::Parsed,
            self::NeedsReview,
            self::ValidationFailed,
            self::WriteFailed,
        ], true);
    }

    public function canBeWritten(): bool
    {
        return in_array($this, [
            self::Parsed,
            self::NeedsReview,
            self::ValidationFailed,
            self::QueuedForWrite,
            self::Writing,
            self::WriteFailed,
        ], true);
    }
}
