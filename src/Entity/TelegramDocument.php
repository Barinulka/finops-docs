<?php

namespace App\Entity;

use App\Enum\Telegram\TelegramDocumentSource;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Repository\TelegramDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: TelegramDocumentRepository::class)]
#[ORM\Index(name: 'idx_telegram_document_user', columns: ['telegram_user_id'])]
#[ORM\Index(name: 'idx_telegram_document_status', columns: ['status'])]
#[ORM\Index(name: 'idx_telegram_document_source', columns: ['source'])]
#[ORM\Index(name: 'idx_telegram_document_chat_id', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_telegram_document_checksum_sha256', columns: ['checksum_sha256'])]
#[ORM\Index(name: 'idx_telegram_document_created_at', columns: ['created_at'])]
class TelegramDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TelegramUser $telegramUser = null;

    #[ORM\OneToOne]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $duplicateOf = null;

    #[ORM\Column(type: 'bigint')]
    private ?string $chatId = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramFileId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramFileUniqueId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $sizeBytes = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column(length: 32, enumType: TelegramDocumentStatus::class)]
    private TelegramDocumentStatus $status = TelegramDocumentStatus::Received;

    #[ORM\Column(length: 32, enumType: TelegramDocumentSource::class)]
    private TelegramDocumentSource $source = TelegramDocumentSource::DirectMessage;

    #[ORM\Column(nullable: true)]
    private ?float $parserConfidence = null;

    #[ORM\Column(nullable: true)]
    private ?bool $autoWriteAllowed = null;

    #[ORM\Column]
    private array $parsedFields = [];

    #[ORM\Column]
    private array $validationErrors = [];

    #[ORM\Column]
    private array $parserWarnings = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $queuedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $parsedAt = null;

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

        $this->receivedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->parsedFields = [];
        $this->validationErrors = [];
        $this->parserWarnings = [];
    }

    public function __toString(): string
    {
        return $this->originalFilename ?: sprintf('Telegram document %s', (string) $this->id);
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getTelegramUser(): ?TelegramUser
    {
        return $this->telegramUser;
    }

    public function setTelegramUser(TelegramUser $telegramUser): static
    {
        $this->telegramUser = $telegramUser;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getDuplicateOf(): ?self
    {
        return $this->duplicateOf;
    }

    public function setDuplicateOf(?self $duplicateOf): static
    {
        $this->duplicateOf = $duplicateOf;

        return $this;
    }

    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    public function setChatId(string|int $chatId): static
    {
        $this->chatId = (string) $chatId;

        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(string|int|null $messageId): static
    {
        $this->messageId = null === $messageId ? null : (string) $messageId;

        return $this;
    }

    public function getTelegramFileId(): ?string
    {
        return $this->telegramFileId;
    }

    public function setTelegramFileId(?string $telegramFileId): static
    {
        $this->telegramFileId = $telegramFileId;

        return $this;
    }

    public function getTelegramFileUniqueId(): ?string
    {
        return $this->telegramFileUniqueId;
    }

    public function setTelegramFileUniqueId(?string $telegramFileUniqueId): static
    {
        $this->telegramFileUniqueId = $telegramFileUniqueId;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(?int $sizeBytes): static
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

    public function getStatus(): TelegramDocumentStatus
    {
        return $this->status;
    }

    public function setStatus(TelegramDocumentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSource(): TelegramDocumentSource
    {
        return $this->source;
    }

    public function setSource(TelegramDocumentSource $source): static
    {
        $this->source = $source;

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

    public function isAutoWriteAllowed(): ?bool
    {
        return $this->autoWriteAllowed;
    }

    public function setAutoWriteAllowed(?bool $autoWriteAllowed): static
    {
        $this->autoWriteAllowed = $autoWriteAllowed;

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

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function setValidationErrors(array $validationErrors): static
    {
        $this->validationErrors = $validationErrors;

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

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

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

    public function getWrittenAt(): ?\DateTimeImmutable
    {
        return $this->writtenAt;
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
}
