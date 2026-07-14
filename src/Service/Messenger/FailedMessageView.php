<?php

namespace App\Service\Messenger;

final readonly class FailedMessageView
{
    public function __construct(
        public string $id,
        public string $messageClass,
        public ?string $failedAt,
        public int $retryCount,
        public ?string $originalTransport,
        public ?string $exceptionClass,
        public ?string $exceptionMessage,
        public ?string $exceptionCode,
    ) {
    }
}
