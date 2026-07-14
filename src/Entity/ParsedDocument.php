<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Repository\ParsedDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ParsedDocumentRepository::class)]
#[ORM\Index(name: 'idx_parsed_document_document_type', columns: ['document_type'])]
#[ORM\Index(name: 'idx_parsed_document_reviewed_by', columns: ['reviewed_by_id'])]
#[ORM\Index(name: 'idx_parsed_document_reviewed_at', columns: ['reviewed_at'])]
class ParsedDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\OneToOne(inversedBy: 'parsedDocument')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\ManyToOne]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column]
    private array $fields = [];

    #[ORM\Column]
    private array $rawPayload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawText = null;

    #[ORM\Column]
    private array $warnings = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 64, enumType: DocumentType::class)]
    private DocumentType $documentType;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->documentType = DocumentType::Unknown;
        $this->fields = [];
        $this->rawPayload = [];
        $this->warnings = [];
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(array $rawPayload): static
    {
        $this->rawPayload = $rawPayload;

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

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function setWarnings(array $warnings): static
    {
        $this->warnings = $warnings;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

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

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }
}
