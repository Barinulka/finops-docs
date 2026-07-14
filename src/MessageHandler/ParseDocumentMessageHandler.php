<?php

namespace App\MessageHandler;

use App\Enum\DocumentStatus;
use App\Message\ParseDocumentMessage;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Document\DocumentParserClient;
use App\Service\Document\DocumentParsingResultApplier;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ParseDocumentMessageHandler
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private UserRepository $userRepository,
        private DocumentParserClient $parserClient,
        private DocumentParsingResultApplier $parsingResultApplier,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        #[Autowire(service: 'documents.storage')]
        private FilesystemOperator $documentsStorage,
    ) {
    }

    public function __invoke(ParseDocumentMessage $message): void
    {
        $document = $this->documentRepository->find($message->documentId);

        if ($document === null) {
            throw new \RuntimeException(sprintf('Document "%s" was not found.', $message->documentId));
        }

        $actor = $this->userRepository->find($message->actorId);

        if ($actor === null) {
            throw new \RuntimeException(sprintf('Actor "%s" was not found.', $message->actorId));
        }

        $document
            ->setStatus(DocumentStatus::Parsing)
            ->setParseError(null);

        $this->entityManager->flush();

        try {
            $storagePath = $document->getStoragePath();

            if ($storagePath === null || $storagePath === '') {
                throw new \RuntimeException('Document storage path is empty.');
            }

            $pdfContent = $this->documentsStorage->read($storagePath);

            $result = $this->parserClient->parsePdf(
                $pdfContent,
                $document->getOriginalFilename() ?? 'document.pdf',
            );

            $this->parsingResultApplier->apply($document, $result, $actor);
        } catch (\Throwable $exception) {
            $document
                ->setStatus(DocumentStatus::Failed)
                ->setParseError($exception->getMessage());

            $this->auditLogger->failed('document', (string) $document->getId(), $exception->getMessage(), [
                'documentId' => (string) $document->getId(),
                'actorId' => (string) $actor->getId(),
            ]);

            $this->entityManager->flush();

            throw $exception;
        }
    }
}