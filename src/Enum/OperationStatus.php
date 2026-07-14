<?php

namespace App\Enum;

enum OperationStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Confirmed => 'Подтверждена',
            self::Cancelled => 'Отменена',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Confirmed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}
