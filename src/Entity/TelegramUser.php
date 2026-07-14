<?php

namespace App\Entity;

use App\Enum\Telegram\TelegramUserRole;
use App\Repository\TelegramUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: TelegramUserRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_telegram_user_telegram_id', fields: ['telegramId'])]
#[ORM\Index(name: 'idx_telegram_user_username', columns: ['username'])]
#[ORM\Index(name: 'idx_telegram_user_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_telegram_user_role', columns: ['role'])]
class TelegramUser
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\Column(type: 'bigint')]
    private ?string $telegramId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $languageCode = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 32, enumType: TelegramUserRole::class)]
    private TelegramUserRole $role = TelegramUserRole::Operator;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\ManyToOne]
    private ?User $linkedUser = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function __toString(): string
    {
        if ($this->username) {
            return sprintf('@%s', $this->username);
        }

        return trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? '')) ?: (string) $this->telegramId;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getTelegramId(): ?string
    {
        return $this->telegramId;
    }

    public function setTelegramId(string|int $telegramId): static
    {
        $this->telegramId = (string) $telegramId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(?string $languageCode): static
    {
        $this->languageCode = $languageCode;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getRole(): TelegramUserRole
    {
        return $this->role;
    }

    public function setRole(TelegramUserRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getLinkedUser(): ?User
    {
        return $this->linkedUser;
    }

    public function setLinkedUser(?User $linkedUser): static
    {
        $this->linkedUser = $linkedUser;

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
