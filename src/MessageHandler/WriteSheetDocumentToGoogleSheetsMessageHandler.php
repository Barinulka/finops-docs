<?php

namespace App\MessageHandler;

use App\Enum\SheetDocumentStatus;
use App\Message\WriteSheetDocumentToGoogleSheetsMessage;
use App\Repository\SheetDocumentRepository;
use App\Service\GoogleSheets\GoogleSheetsClient;
use App\Service\SheetDocument\SheetDocumentGoogleSheetRowMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WriteSheetDocumentToGoogleSheetsMessageHandler
{
    public function __construct(
        private SheetDocumentRepository $sheetDocumentRepository,
        private SheetDocumentGoogleSheetRowMapper $rowMapper,
        private GoogleSheetsClient $googleSheetsClient,
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

        $sheetDocument
            ->setStatus(SheetDocumentStatus::Writing)
            ->setErrorMessage(null)
            ->setFailedAt(null);

        $this->entityManager->flush();

        try {
            $row = $this->rowMapper->map($sheetDocument);

            $this->googleSheetsClient->appendSparseRow($row);

            $sheetDocument
                ->setStatus(SheetDocumentStatus::Written)
                ->setWrittenAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $sheetDocument
                ->setStatus(SheetDocumentStatus::WriteFailed)
                ->setErrorMessage($exception->getMessage())
                ->setFailedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            throw $exception;
        }
    }
}
