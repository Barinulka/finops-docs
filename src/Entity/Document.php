<?php

namespace App\Entity;

use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Index(name: 'idx_document_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_document_uploaded_by', columns: ['uploaded_by_id'])]
#[ORM\Index(name: 'idx_document_status', columns: ['status'])]
#[ORM\Index(name: 'idx_document_type', columns: ['type'])]
#[ORM\Index(name: 'idx_document_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_document_checksum_sha256', columns: ['checksum_sha256'])]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $parserVersion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $parseError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $queuedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $parsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 32, enumType: DocumentStatus::class)]
    private DocumentStatus $status;

    #[ORM\Column(length: 64, enumType: DocumentType::class)]
    private DocumentType $type;

    #[ORM\OneToOne(targetEntity: ParsedDocument::class, mappedBy: 'document')]
    private ?ParsedDocument $parsedDocument = null;

    /**
     * @var Collection<int, Operation>
     */
    #[ORM\OneToMany(targetEntity: Operation::class, mappedBy: 'sourceDocument')]
    private Collection $operations;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->status = DocumentStatus::Uploaded;
        $this->type = DocumentType::Unknown;
        $this->createdAt = $now;
        $this->updatedAt = $now;

        $this->operations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->originalFilename ?: sprintf('Document %s', (string) $this->id);
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;

        return $this;
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

    public function getParserVersion(): ?string
    {
        return $this->parserVersion;
    }

    public function setParserVersion(?string $parserVersion): static
    {
        $this->parserVersion = $parserVersion;

        return $this;
    }

    public function getParseError(): ?string
    {
        return $this->parseError;
    }

    public function setParseError(?string $parseError): static
    {
        $this->parseError = $parseError;

        return $this;
    }

    public function getQueuedAt(): ?\DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function setQueuedAt(?\DateTimeImmutable $queuedAt): static
    {
        $this->queuedAt = $queuedAt;

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

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
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

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getParsedDocument(): ?ParsedDocument
    {
        return $this->parsedDocument;
    }

    public function setParsedDocument(?ParsedDocument $parsedDocument): static
    {
        $this->parsedDocument = $parsedDocument;

        return $this;
    }

    /**
     * @return Collection<int, Operation>
     */
    public function getOperations(): Collection
    {
        return $this->operations;
    }

    public function getRelatedOperation(): ?Operation
    {
        $operation = $this->operations->first();

        return $operation instanceof Operation ? $operation : null;
    }

    public function getParsedConfidence(): ?float
    {
        return $this->parsedDocument?->getConfidence();
    }

    public function getParsedConfidenceText(): string
    {
        $confidence = $this->parsedDocument?->getConfidence();

        if ($confidence === null) {
            return 'Нет данных';
        }

        return sprintf('%.2f', $confidence);
    }

    /**
     * @return array<mixed>
     */
    public function getParsedWarnings(): array
    {
        return $this->parsedDocument?->getWarnings() ?? [];
    }

    public function getParsedWarningsText(): string
    {
        $warnings = $this->parsedDocument?->getWarnings() ?? [];

        if ($warnings === []) {
            return 'Нет';
        }

        return json_encode(
            $warnings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: 'Нет';
    }

    /**
     * @return array<mixed>
     */
    public function getParsedFields(): array
    {
        return $this->parsedDocument?->getFields() ?? [];
    }

    public function getParsedFieldsText(): string
    {
        $fields = $this->parsedDocument?->getFields() ?? [];

        if ($fields === []) {
            return 'Нет данных';
        }

        return json_encode(
            $fields,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: 'Нет данных';
    }

    public function getParsedRawText(): string
    {
        $rawText = $this->parsedDocument?->getRawText();

        if ($rawText === null || trim($rawText) === '') {
            return 'Нет данных';
        }

        return $rawText;
    }

    public function getRelatedOperationId(): ?string
    {
        $operation = $this->getRelatedOperation();

        return $operation instanceof Operation ? (string) $operation->getId() : null;
    }
}
