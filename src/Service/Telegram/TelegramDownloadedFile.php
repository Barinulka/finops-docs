<?php

namespace App\Service\Telegram;

final readonly class TelegramDownloadedFile
{
    public function __construct(
        public string $contents,
        public string $checksumSha256,
        public int $sizeBytes,
    ) {
    }
}
