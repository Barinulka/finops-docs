<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentStatus;

final readonly class TelegramDocumentProcessingService
{
    public function __construct(
        private TelegramFileDownloader $telegramFileDownloader,
        private TelegramDocumentParser $telegramDocumentParser,
        private TelegramDocumentBusinessValidator $businessValidator,
    ) {
    }

    public function process(TelegramDocument $telegramDocument): void
    {
        try {
            $downloadedFile = $this->telegramFileDownloader->download($telegramDocument->getTelegramFileId());

            $telegramDocument->setChecksumSha256($downloadedFile->checksumSha256);
            $telegramDocument->setSizeBytes($downloadedFile->sizeBytes);

            $this->telegramDocumentParser->parse($telegramDocument, $downloadedFile->contents);

            /*
             * Парсер сам выставляет Parsed / NeedsReview / Failed.
             * Бизнес-проверки имеет смысл запускать только если технический парсинг прошел.
             */
            if (!in_array($telegramDocument->getStatus(), [
                TelegramDocumentStatus::Parsed,
                TelegramDocumentStatus::NeedsReview,
            ], true)) {
                return;
            }

            $validationResult = $this->businessValidator->validate($telegramDocument);

            if (!$validationResult->valid) {
                $telegramDocument->setStatus(TelegramDocumentStatus::ValidationFailed);
                $telegramDocument->setValidationErrors($validationResult->errors);
                $telegramDocument->setAutoWriteAllowed(false);
            }
        } catch (\Throwable $exception) {
            /*
             * Webhook не должен падать из-за ошибки скачивания, OCR, parser API или валидации.
             * Пользователь получит понятное сообщение, а техническая причина останется в админке.
             */
            $telegramDocument->setStatus(TelegramDocumentStatus::Failed);
            $telegramDocument->setErrorMessage($exception->getMessage());
            $telegramDocument->setFailedAt(new \DateTimeImmutable());
            $telegramDocument->setAutoWriteAllowed(false);
        }
    }
}
