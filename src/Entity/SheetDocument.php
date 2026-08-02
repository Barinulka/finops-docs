<?php

namespace App\Entity;

use App\Enum\SheetDocumentStatus;
use App\Repository\SheetDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SheetDocumentRepository::class)]
#[ORM\Index(name: 'idx_sheet_document_uploaded_by', columns: ['uploaded_by_id'])]
#[ORM\Index(name: 'idx_sheet_document_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_sheet_document_checksum_sha256', columns: ['checksum_sha256'])]
#[ORM\Index(name: 'idx_sheet_document_uploaded_at', columns: ['uploaded_at'])]
class SheetDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploadedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 512)]
    private ?string $storagePath = null;

    #[ORM\Column(length: 128)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?int $sizeBytes = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column(length: 32, enumType: SheetDocumentStatus::class)]
    private SheetDocumentStatus $status = SheetDocumentStatus::Uploaded;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $parserVersion = null;

    #[ORM\Column(nullable: true)]
    private ?float $parserConfidence = null;

    #[ORM\Column]
    private array $parsedFields = [];

    #[ORM\Column]
    private array $parserWarnings = [];

    #[ORM\Column]
    private array $validationErrors = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $queuedForParsingAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $parsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $queuedForWriteAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $writtenAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->status = SheetDocumentStatus::Uploaded;
        $this->parsedFields = [];
        $this->parserWarnings = [];
        $this->validationErrors = [];
        $this->uploadedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function __toString(): string
    {
        return $this->originalFilename ?: sprintf('Sheet document %s', (string) $this->id);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getChecksumSha256(): ?string
    {
        return $this->checksumSha256;
    }

    public function setChecksumSha256(?string $checksumSha256): static
    {
        $this->checksumSha256 = $checksumSha256;

        return $this;
    }

    public function getStatus(): SheetDocumentStatus
    {
        return $this->status;
    }

    public function setStatus(SheetDocumentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getParserVersion(): ?string
    {
        return $this->parserVersion;
    }

    public function setParserVersion(?string $parserVersion): static
    {
        $this->parserVersion = $parserVersion;

        return $this;
    }

    public function getParserConfidence(): ?float
    {
        return $this->parserConfidence;
    }

    public function setParserConfidence(?float $parserConfidence): static
    {
        $this->parserConfidence = $parserConfidence;

        return $this;
    }

    public function getParsedFields(): array
    {
        return $this->parsedFields;
    }

    public function setParsedFields(array $parsedFields): static
    {
        $this->parsedFields = $parsedFields;

        return $this;
    }

    public function getParserWarnings(): array
    {
        return $this->parserWarnings;
    }

    public function setParserWarnings(array $parserWarnings): static
    {
        $this->parserWarnings = $parserWarnings;

        return $this;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function setValidationErrors(array $validationErrors): static
    {
        $this->validationErrors = $validationErrors;

        return $this;
    }

    public function getRawText(): ?string
    {
        return $this->rawText;
    }

    public function setRawText(?string $rawText): static
    {
        $this->rawText = $rawText;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getQueuedForParsingAt(): ?\DateTimeImmutable
    {
        return $this->queuedForParsingAt;
    }

    public function setQueuedForParsingAt(?\DateTimeImmutable $queuedForParsingAt): static
    {
        $this->queuedForParsingAt = $queuedForParsingAt;

        return $this;
    }

    public function getParsedAt(): ?\DateTimeImmutable
    {
        return $this->parsedAt;
    }

    public function setParsedAt(?\DateTimeImmutable $parsedAt): static
    {
        $this->parsedAt = $parsedAt;

        return $this;
    }

    public function getQueuedForWriteAt(): ?\DateTimeImmutable
    {
        return $this->queuedForWriteAt;
    }

    public function setQueuedForWriteAt(?\DateTimeImmutable $queuedForWriteAt): static
    {
        $this->queuedForWriteAt = $queuedForWriteAt;

        return $this;
    }

    public function getWrittenAt(): ?\DateTimeImmutable
    {
        return $this->writtenAt;
    }

    public function isWrittenToSheet(): bool
    {
        return $this->writtenAt !== null;
    }

    public function setWrittenAt(?\DateTimeImmutable $writtenAt): static
    {
        $this->writtenAt = $writtenAt;

        return $this;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(?\DateTimeImmutable $failedAt): static
    {
        $this->failedAt = $failedAt;

        return $this;
    }
}
