<?php

namespace App\Service\SheetDocument;

use App\Entity\SheetDocument;
use App\Entity\User;
use App\Enum\SheetDocumentStatus;
use App\Message\ParseSheetDocumentMessage;
use App\Repository\SheetDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SheetDocumentUploadService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private SheetDocumentRepository $sheetDocumentRepository,
        #[Autowire(service: 'documents.storage')]
        private FilesystemOperator $storage,
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function upload(array $files, User $uploadedBy): SheetDocumentUploadResult
    {
        $result = new SheetDocumentUploadResult();
        $now = new \DateTimeImmutable();

        foreach ($files as $file) {
            $checksumSha256 = hash_file('sha256', $file->getPathname());

            if ($checksumSha256 === false) {
                throw new \RuntimeException(sprintf(
                    'Не удалось посчитать checksum файла "%s".',
                    $file->getClientOriginalName(),
                ));
            }

            $existingDocument = $this->sheetDocumentRepository->findOneBy([
                'checksumSha256' => $checksumSha256,
            ]);

            if ($existingDocument instanceof SheetDocument) {
                $result->addSkippedDuplicate($file->getClientOriginalName(), $existingDocument);

                continue;
            }

            $storagePath = $this->buildStoragePath($now);

            $sheetDocument = new SheetDocument();
            $sheetDocument
                ->setUploadedBy($uploadedBy)
                ->setOriginalFilename($file->getClientOriginalName())
                ->setStoragePath($storagePath)
                ->setMimeType($file->getMimeType() ?: 'application/pdf')
                ->setSizeBytes($file->getSize())
                ->setChecksumSha256($checksumSha256)
                ->setUploadedAt($now)
                ->setStatus(SheetDocumentStatus::QueuedForParsing)
                ->setQueuedForParsingAt($now);

            $stream = fopen($file->getPathname(), 'rb');

            if ($stream === false) {
                throw new \RuntimeException(sprintf(
                    'Не удалось открыть файл "%s" для чтения.',
                    $file->getClientOriginalName(),
                ));
            }

            try {
                $this->storage->writeStream($storagePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $this->entityManager->persist($sheetDocument);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new ParseSheetDocumentMessage((string) $sheetDocument->getId()));

            $result->addUploadedDocument($sheetDocument);
        }

        return $result;
    }

    private function buildStoragePath(\DateTimeImmutable $uploadedAt): string
    {
        return sprintf(
            'sheet-documents/%s/%s.pdf',
            $uploadedAt->format('Y/m/d'),
            (string) new Ulid(),
        );
    }
}
