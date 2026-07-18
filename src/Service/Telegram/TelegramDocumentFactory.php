<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramUser;
use App\Enum\Telegram\TelegramDocumentSource;
use App\Enum\Telegram\TelegramDocumentStatus;

final readonly class TelegramDocumentFactory
{
    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $document
     */
    public function createFromTelegramMessage(
        TelegramUser $telegramUser,
        array $message,
        array $document,
    ): TelegramDocument {
        $fileName = $document['file_name'] ?? null;

        if ($fileName !== null && !is_string($fileName)) {
            $fileName = null;
        }

        $telegramDocument = new TelegramDocument();
        $telegramDocument->setTelegramUser($telegramUser);
        $telegramDocument->setChatId($message['chat']['id']);
        $telegramDocument->setMessageId($message['message_id'] ?? null);
        $telegramDocument->setTelegramFileId($document['file_id']);
        $telegramDocument->setTelegramFileUniqueId($document['file_unique_id']);
        $telegramDocument->setOriginalFilename($fileName);
        $telegramDocument->setMimeType($document['mime_type']);
        $telegramDocument->setSizeBytes($document['file_size']);
        $telegramDocument->setStatus(TelegramDocumentStatus::Received);
        $telegramDocument->setSource(TelegramDocumentSource::DirectMessage);

        return $telegramDocument;
    }
}
