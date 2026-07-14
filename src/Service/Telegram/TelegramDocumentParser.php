<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Service\Document\DocumentParserClient;

final readonly class TelegramDocumentParser
{
    public function __construct(
        private DocumentParserClient $documentParserClient,
    ) {
    }

    /**
     * Парсим PDF из Telegram через Python parser API.
     *
     * Этот сервис специально работает только с TelegramDocument:
     * - не создает CRM Document;
     * - не пишет в Google Sheets;
     * - только вызывает parser API и сохраняет результат парсинга.
     */
    public function parse(TelegramDocument $telegramDocument, string $pdfContent): void
    {
        $telegramDocument->setStatus(TelegramDocumentStatus::Parsing);

        try {
            $filename = $telegramDocument->getOriginalFilename() ?: 'telegram-document.pdf';

            $result = $this->documentParserClient->parsePdf($pdfContent, $filename);

            $fields = $result['fields'] ?? [];
            $warnings = $result['warnings'] ?? [];
            $rawText = $result['rawText'] ?? null;
            $confidence = $result['confidence'] ?? null;

            $telegramDocument->setParsedFields(is_array($fields) ? $fields : []);
            $telegramDocument->setParserWarnings(is_array($warnings) ? $warnings : []);
            $telegramDocument->setRawText(is_string($rawText) ? $rawText : null);
            $telegramDocument->setParserConfidence(is_numeric($confidence) ? (float) $confidence : null);
            $telegramDocument->setParsedAt(new \DateTimeImmutable());

            /*
             * Пока считаем документ небезопасным для автозаписи, если:
             * - parser не дал confidence;
             * - confidence ниже 0.8;
             * - parser вернул предупреждения.
             */
            if (
                $telegramDocument->getParserConfidence() === null
                || $telegramDocument->getParserConfidence() < 0.8
                || $warnings !== []
            ) {
                $telegramDocument->setStatus(TelegramDocumentStatus::NeedsReview);
                $telegramDocument->setAutoWriteAllowed(false);

                return;
            }

            $telegramDocument->setStatus(TelegramDocumentStatus::Parsed);
            $telegramDocument->setAutoWriteAllowed(true);
        } catch (\Throwable $exception) {
            /*
             * Не роняем webhook. Ошибку сохраняем в БД,
             * чтобы ее было видно в админке.
             */
            $telegramDocument->setStatus(TelegramDocumentStatus::Failed);
            $telegramDocument->setErrorMessage($exception->getMessage());
            $telegramDocument->setFailedAt(new \DateTimeImmutable());
            $telegramDocument->setAutoWriteAllowed(false);
        }
    }
}
