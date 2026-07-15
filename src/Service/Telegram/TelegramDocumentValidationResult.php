<?php

namespace App\Service\Telegram;

final readonly class TelegramDocumentValidationResult
{
    public function __construct(
        public bool $valid,
        /** @var list<string> */
        public array $errors = [],
    ) {
    }
}
