<?php

namespace App\MessageHandler;

use App\Enum\SheetDocumentStatus;
use App\Message\WriteSheetDocumentToGoogleSheetsMessage;
use App\Repository\SheetDocumentRepository;
use App\Service\GoogleSheets\GoogleSheetsClient;
use App\Service\SheetDocument\SheetDocumentGoogleSheetRowMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Entity\GoogleSheetAppendLog;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Service\GoogleSheets\GoogleSheetsConfig;

#[AsMessageHandler]
final readonly class WriteSheetDocumentToGoogleSheetsMessageHandler
{
    public function __construct(
        private SheetDocumentRepository $sheetDocumentRepository,
        private SheetDocumentGoogleSheetRowMapper $rowMapper,
        private GoogleSheetsClient $googleSheetsClient,
        private GoogleSheetsConfig $googleSheetsConfig,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WriteSheetDocumentToGoogleSheetsMessage $message): void
    {
        $sheetDocument = $this->sheetDocumentRepository->find($message->sheetDocumentId);

        if ($sheetDocument === null) {
            throw new \RuntimeException(sprintf('Sheet document "%s" was not found.', $message->sheetDocumentId));
        }

        if ($sheetDocument->getStatus() === SheetDocumentStatus::Written) {
            return;
        }

        if (!$sheetDocument->getStatus()->canBeWritten()) {
            throw new \RuntimeException(sprintf(
                'Sheet document with status "%s" cannot be written to Google Sheets.',
                $sheetDocument->getStatus()->value,
            ));
        }

        $row = $this->rowMapper->map($sheetDocument);

        $log = new GoogleSheetAppendLog();
        $log
            ->setSheetDocument($sheetDocument)
            ->setRequestedBy($sheetDocument->getUploadedBy())
            ->setSpreadsheetId($this->googleSheetsConfig->spreadsheetId)
            ->setSheetName($this->googleSheetsConfig->sheetName)
            ->setPayload($row)
            ->setStatus(GoogleSheetAppendStatus::Pending);

        $sheetDocument
            ->setStatus(SheetDocumentStatus::Writing)
            ->setErrorMessage(null)
            ->setFailedAt(null);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        try {
            $response = $this->googleSheetsClient->appendSparseRow($row);

            $log
                ->setStatus(GoogleSheetAppendStatus::Success)
                ->setAppendedRange($this->extractUpdatedRange($response))
                ->setWrittenAt(new \DateTimeImmutable());

            $sheetDocument
                ->setStatus(SheetDocumentStatus::Written)
                ->setWrittenAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $log
                ->setStatus(GoogleSheetAppendStatus::Failed)
                ->setErrorMessage($exception->getMessage());

            $sheetDocument
                ->setStatus(SheetDocumentStatus::WriteFailed)
                ->setErrorMessage($exception->getMessage())
                ->setFailedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractUpdatedRange(array $response): ?string
    {
        $responses = $response['responses'] ?? null;

        if (!is_array($responses) || $responses === []) {
            return null;
        }

        $firstResponse = $responses[0] ?? null;

        if (!is_array($firstResponse)) {
            return null;
        }

        $updatedRange = $firstResponse['updatedRange'] ?? null;

        return is_string($updatedRange) ? $updatedRange : null;
    }
}
