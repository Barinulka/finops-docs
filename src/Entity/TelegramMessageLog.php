<?php

namespace App\Entity;

use App\Enum\Telegram\TelegramMessageDirection;
use App\Enum\Telegram\TelegramMessageStatus;
use App\Repository\TelegramMessageLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: TelegramMessageLogRepository::class)]
#[ORM\Index(name: 'idx_telegram_message_log_user', columns: ['telegram_user_id'])]
#[ORM\Index(name: 'idx_telegram_message_log_document', columns: ['telegram_document_id'])]
#[ORM\Index(name: 'idx_telegram_message_log_chat_id', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_telegram_message_log_status', columns: ['status'])]
#[ORM\Index(name: 'idx_telegram_message_log_created_at', columns: ['created_at'])]
class TelegramMessageLog
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    private ?TelegramUser $telegramUser = null;

    #[ORM\ManyToOne]
    private ?TelegramDocument $telegramDocument = null;

    #[ORM\Column(type: 'bigint')]
    private ?string $chatId = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 32, enumType: TelegramMessageDirection::class)]
    private TelegramMessageDirection $direction;

    #[ORM\Column(length: 32, enumType: TelegramMessageStatus::class)]
    private TelegramMessageStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\Column]
    private array $payload = [];

    #[ORM\Column]
    private array $responsePayload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->direction = TelegramMessageDirection::Incoming;
        $this->status = TelegramMessageStatus::Received;
        $this->payload = [];
        $this->responsePayload = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getTelegramUser(): ?TelegramUser
    {
        return $this->telegramUser;
    }

    public function setTelegramUser(?TelegramUser $telegramUser): static
    {
        $this->telegramUser = $telegramUser;

        return $this;
    }

    public function getTelegramDocument(): ?TelegramDocument
    {
        return $this->telegramDocument;
    }

    public function setTelegramDocument(?TelegramDocument $telegramDocument): static
    {
        $this->telegramDocument = $telegramDocument;

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

    public function getDirection(): TelegramMessageDirection
    {
        return $this->direction;
    }

    public function setDirection(TelegramMessageDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getStatus(): TelegramMessageStatus
    {
        return $this->status;
    }

    public function setStatus(TelegramMessageStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

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

    public function getResponsePayload(): array
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(array $responsePayload): static
    {
        $this->responsePayload = $responsePayload;

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
