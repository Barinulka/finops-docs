<?php

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\User;
use App\Form\Model\DocumentUploadData;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use App\Enum\DocumentStatus;
use App\Message\ParseDocumentMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class DocumentUploadService
{
    public function __construct(
        #[Autowire(service: 'documents.storage')]
        private FilesystemOperator $documentsStorage,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function upload(DocumentUploadData $data, User $uploadedBy): Document
    {
        if ($data->client === null) {
            throw new \InvalidArgumentException('Client is required.');
        }

        if (!$data->file instanceof UploadedFile) {
            throw new \InvalidArgumentException('Uploaded PDF file is required.');
        }

        $file = $data->file;
        $documentId = new Ulid();
        $checksumSha256 = hash_file('sha256', $file->getPathname());

        if ($checksumSha256 === false) {
            throw new \RuntimeException('Unable to calculate file checksum.');
        }

        $storagePath = sprintf(
            '%s/%s/%s.pdf',
            $data->client->getId(),
            (new \DateTimeImmutable())->format('Y/m/d'),
            $documentId,
        );

        $stream = fopen($file->getPathname(), 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        try {
            $this->documentsStorage->writeStream($storagePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $sizeBytes = $file->getSize();

        if ($sizeBytes === null) {
            throw new \RuntimeException('Unable to determine uploaded file size.');
        }

        $document = new Document();
        $document
            ->setClient($data->client)
            ->setUploadedBy($uploadedBy)
            ->setOriginalFilename($file->getClientOriginalName())
            ->setStoragePath($storagePath)
            ->setMimeType($file->getMimeType() ?? 'application/pdf')
            ->setSizeBytes($sizeBytes)
            ->setChecksumSha256($checksumSha256)
            ->setStatus(DocumentStatus::Queued)
            ->setQueuedAt(new \DateTimeImmutable())
        ;

        $this->entityManager->persist($document);

        $this->auditLogger->uploaded('document', (string) $document->getId(), [
            'clientId' => (string) $data->client->getId(),
            'originalFilename' => $file->getClientOriginalName(),
            'storagePath' => $storagePath,
            'sizeBytes' => $sizeBytes,
            'checksumSha256' => $checksumSha256,
        ]);

        $this->entityManager->flush();

        $this->messageBus->dispatch(new ParseDocumentMessage(
            (string) $document->getId(),
            (string) $uploadedBy->getId(),
        ));

        return $document;
    }
}
