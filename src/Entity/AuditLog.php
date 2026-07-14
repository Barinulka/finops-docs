<?php

namespace App\Entity;

use App\Enum\AuditAction;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(name: 'idx_audit_log_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'idx_audit_log_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_log_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_log_created_at', columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    private ?User $actor = null;

    #[ORM\Column(length: 128)]
    private ?string $entityType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $message = null;

    #[ORM\Column]
    private array $oldValues = [];

    #[ORM\Column]
    private array $newValues = [];

    #[ORM\Column]
    private array $context = [];

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 32, enumType: AuditAction::class)]
    private AuditAction $action;

    public function __construct()
    {
        $this->oldValues = [];
        $this->newValues = [];
        $this->context = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getOldValues(): array
    {
        return $this->oldValues;
    }

    public function setOldValues(array $oldValues): static
    {
        $this->oldValues = $oldValues;

        return $this;
    }

    public function getNewValues(): array
    {
        return $this->newValues;
    }

    public function setNewValues(array $newValues): static
    {
        $this->newValues = $newValues;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function setAction(AuditAction $action): static
    {
        $this->action = $action;

        return $this;
    }
}
