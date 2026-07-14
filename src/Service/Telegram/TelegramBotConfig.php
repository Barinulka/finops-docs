<?php

namespace App\Service\Telegram;

final readonly class TelegramBotConfig
{
    public function __construct(
        public string $botToken,
        public string $webhookSecret,
        public int $allowedMaxFileSize,
    ) {
    }
}
