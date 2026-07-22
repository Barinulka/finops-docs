<?php

namespace App\Service\Telegram;

use App\Entity\GoogleSheetAppendLog;
use App\Entity\TelegramDocument;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Service\GoogleSheets\GoogleSheetsClient;
use App\Service\GoogleSheets\GoogleSheetsConfig;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TelegramDocumentGoogleSheetWriter
{
    public function __construct(
        private GoogleSheetsClient $googleSheetsClient,
        private GoogleSheetsConfig $googleSheetsConfig,
        private TelegramDocumentGoogleSheetRowMapper $rowMapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function write(TelegramDocument $telegramDocument): GoogleSheetAppendLog
    {
        /*
         * В таблицу пишем только документы, которые уже распарсены
         * или были помечены как требующие проверки.
         *
         * NeedsReview оставляем разрешенным для ручной записи из админки:
         * оператор посмотрел поля и все равно решил отправить в таблицу.
         */
        if (!in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::Parsed,
            TelegramDocumentStatus::NeedsReview,
            TelegramDocumentStatus::ValidationFailed,
        ], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Telegram document with status "%s" cannot be written to Google Sheets.',
                $telegramDocument->getStatus()->value,
            ));
        }

        $cells = $this->rowMapper->mapCells($telegramDocument);

        $log = new GoogleSheetAppendLog();
        $log->setTelegramDocument($telegramDocument);
        $log->setSpreadsheetId($this->googleSheetsConfig->spreadsheetId);
        $log->setSheetName($this->googleSheetsConfig->sheetName);
        $log->setPayload([
            'cells' => $cells,
        ]);

        $this->entityManager->persist($log);

        try {
            $response = $this->googleSheetsClient->appendSparseRow($cells);

            /*
             * Google API обычно возвращает updates.updatedRange.
             * Это удобно хранить: потом видно, куда именно была записана строка.
             */
            $updatedRange = $response['updates']['updatedRange'] ?? null;

            $log->setStatus(GoogleSheetAppendStatus::Success);
            $log->setWrittenAt(new \DateTimeImmutable());
            $log->setAppendedRange(is_string($updatedRange) ? $updatedRange : null);
            $log->setPayload([
                'cells' => $cells,
                'response' => $response,
            ]);

            $telegramDocument->setStatus(TelegramDocumentStatus::Written);
            $telegramDocument->setWrittenAt(new \DateTimeImmutable());
            $telegramDocument->setErrorMessage(null);
        } catch (\Throwable $exception) {
            /*
             * Ошибку сохраняем в log и в сам TelegramDocument.
             * Так ее будет видно и в журнале записи, и в списке документов.
             */
            $log->setStatus(GoogleSheetAppendStatus::Failed);
            $log->setErrorMessage($exception->getMessage());

            $telegramDocument->setStatus(TelegramDocumentStatus::Failed);
            $telegramDocument->setFailedAt(new \DateTimeImmutable());
            $telegramDocument->setErrorMessage($exception->getMessage());
        }

        $this->entityManager->flush();

        return $log;
    }
}
