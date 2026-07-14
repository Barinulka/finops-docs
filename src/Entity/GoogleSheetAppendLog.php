<?php

namespace App\Entity;

use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Repository\GoogleSheetAppendLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: GoogleSheetAppendLogRepository::class)]
#[ORM\Index(name: 'idx_google_sheet_append_log_document', columns: ['telegram_document_id'])]
#[ORM\Index(name: 'idx_google_sheet_append_log_status', columns: ['status'])]
#[ORM\Index(name: 'idx_google_sheet_append_log_created_at', columns: ['created_at'])]
class GoogleSheetAppendLog
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TelegramDocument $telegramDocument = null;

    #[ORM\Column(length: 255)]
    private ?string $spreadsheetId = null;

    #[ORM\Column(length: 255)]
    private ?string $sheetName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $appendedRange = null;

    #[ORM\Column]
    private array $payload = [];

    #[ORM\Column(length: 32, enumType: GoogleSheetAppendStatus::class)]
    private GoogleSheetAppendStatus $status = GoogleSheetAppendStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $writtenAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->payload = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getTelegramDocument(): ?TelegramDocument
    {
        return $this->telegramDocument;
    }

    public function setTelegramDocument(TelegramDocument $telegramDocument): static
    {
        $this->telegramDocument = $telegramDocument;

        return $this;
    }

    public function getSpreadsheetId(): ?string
    {
        return $this->spreadsheetId;
    }

    public function setSpreadsheetId(string $spreadsheetId): static
    {
        $this->spreadsheetId = $spreadsheetId;

        return $this;
    }

    public function getSheetName(): ?string
    {
        return $this->sheetName;
    }

    public function setSheetName(string $sheetName): static
    {
        $this->sheetName = $sheetName;

        return $this;
    }

    public function getAppendedRange(): ?string
    {
        return $this->appendedRange;
    }

    public function setAppendedRange(?string $appendedRange): static
    {
        $this->appendedRange = $appendedRange;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getStatus(): GoogleSheetAppendStatus
    {
        return $this->status;
    }

    public function setStatus(GoogleSheetAppendStatus $status): static
    {
        $this->status = $status;

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

    public function getWrittenAt(): ?\DateTimeImmutable
    {
        return $this->writtenAt;
    }

    public function setWrittenAt(?\DateTimeImmutable $writtenAt): static
    {
        $this->writtenAt = $writtenAt;

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
}
