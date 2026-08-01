<?php

namespace App\MessageHandler;

use App\Enum\SheetDocumentStatus;
use App\Message\ParseSheetDocumentMessage;
use App\Repository\SheetDocumentRepository;
use App\Service\SheetDocument\SheetDocumentParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ParseSheetDocumentMessageHandler
{
    public function __construct(
        private SheetDocumentRepository $sheetDocumentRepository,
        private SheetDocumentParser $sheetDocumentParser,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'documents.storage')]
        private FilesystemOperator $documentsStorage,
    ) {
    }

    public function __invoke(ParseSheetDocumentMessage $message): void
    {
        $sheetDocument = $this->sheetDocumentRepository->find($message->sheetDocumentId);

        if ($sheetDocument === null) {
            throw new \RuntimeException(sprintf('Sheet document "%s" was not found.', $message->sheetDocumentId));
        }

        $sheetDocument
            ->setStatus(SheetDocumentStatus::Parsing)
            ->setErrorMessage(null)
            ->setFailedAt(null);

        $this->entityManager->flush();

        try {
            $storagePath = $sheetDocument->getStoragePath();

            if ($storagePath === null || $storagePath === '') {
                throw new \RuntimeException('Sheet document storage path is empty.');
            }

            $pdfContent = $this->documentsStorage->read($storagePath);

            $this->sheetDocumentParser->parse($sheetDocument, $pdfContent);

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $sheetDocument
                ->setStatus(SheetDocumentStatus::Failed)
                ->setErrorMessage($exception->getMessage())
                ->setFailedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            throw $exception;
        }
    }
}
