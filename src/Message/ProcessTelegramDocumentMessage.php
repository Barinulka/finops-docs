<?php

namespace App\Message;

final readonly class ProcessTelegramDocumentMessage
{
    public function __construct(
        public string $telegramDocumentId,
    ) {
    }
}
