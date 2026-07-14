<?php

namespace App\Enum;

enum DocumentType: string
{
    case Unknown = 'unknown';
    case PaymentInstruction = 'payment_instruction';
    case ApplicationForm = 'application_form';
    case AsstraApplication = 'asstra_application';
    case SubagentInstruction = 'subagent_instruction';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Неизвестный',
            self::PaymentInstruction => 'Платежное поручение',
            self::ApplicationForm => 'Заявка к агентскому договору',
            self::AsstraApplication => 'Заявка ASSTRA',
            self::SubagentInstruction => 'Субагентское поручение',
        };
    }
}
