<?php

namespace App\Entity;

use App\Enum\OperationStatus;
use App\Enum\OperationType;
use App\Repository\OperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\Index(name: 'idx_operation_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_operation_source_document', columns: ['source_document_id'])]
#[ORM\Index(name: 'idx_operation_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_operation_confirmed_by', columns: ['confirmed_by_id'])]
#[ORM\Index(name: 'idx_operation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_operation_type', columns: ['type'])]
#[ORM\Index(name: 'idx_operation_operation_date', columns: ['operation_date'])]
#[ORM\Index(name: 'idx_operation_external_reference', columns: ['external_reference'])]
#[ORM\Index(name: 'idx_operation_created_at', columns: ['created_at'])]
class Operation
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\Column(length: 32, enumType: OperationStatus::class)]
    private OperationStatus $status;

    #[ORM\Column(length: 32, enumType: OperationType::class)]
    private OperationType $type;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $operationDate = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $contractNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $purpose = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $paymentAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $paymentCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $exchangeRateRaw = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2, nullable: true)]
    private ?string $agencyFeeAmountRub = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2, nullable: true)]
    private ?string $totalAmountRub = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $executionTermRaw = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $executionDueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiaryName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiaryBankName = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $beneficiarySwift = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $beneficiaryAccount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $beneficiaryRawDetails = null;

    #[ORM\Column]
    private array $metadata = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'operations')]
    private ?Document $sourceDocument = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    private ?User $confirmedBy = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->status = OperationStatus::Draft;
        $this->type = OperationType::Other;
        $this->metadata = [];
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getStatus(): OperationStatus
    {
        return $this->status;
    }

    public function setStatus(OperationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): OperationType
    {
        return $this->type;
    }

    public function setType(OperationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getOperationDate(): ?\DateTimeImmutable
    {
        return $this->operationDate;
    }

    public function setOperationDate(?\DateTimeImmutable $operationDate): static
    {
        $this->operationDate = $operationDate;

        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): static
    {
        $this->externalReference = $externalReference;

        return $this;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(?string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getPaymentAmount(): ?string
    {
        return $this->paymentAmount;
    }

    public function setPaymentAmount(?string $paymentAmount): static
    {
        $this->paymentAmount = $paymentAmount;

        return $this;
    }

    public function getPaymentCurrency(): ?string
    {
        return $this->paymentCurrency;
    }

    public function setPaymentCurrency(?string $paymentCurrency): static
    {
        $this->paymentCurrency = $paymentCurrency;

        return $this;
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getExchangeRateRaw(): ?string
    {
        return $this->exchangeRateRaw;
    }

    public function setExchangeRateRaw(?string $exchangeRateRaw): static
    {
        $this->exchangeRateRaw = $exchangeRateRaw;

        return $this;
    }

    public function getAgencyFeeAmountRub(): ?string
    {
        return $this->agencyFeeAmountRub;
    }

    public function setAgencyFeeAmountRub(?string $agencyFeeAmountRub): static
    {
        $this->agencyFeeAmountRub = $agencyFeeAmountRub;

        return $this;
    }

    public function getTotalAmountRub(): ?string
    {
        return $this->totalAmountRub;
    }

    public function setTotalAmountRub(?string $totalAmountRub): static
    {
        $this->totalAmountRub = $totalAmountRub;

        return $this;
    }

    public function getExecutionTermRaw(): ?string
    {
        return $this->executionTermRaw;
    }

    public function setExecutionTermRaw(?string $executionTermRaw): static
    {
        $this->executionTermRaw = $executionTermRaw;

        return $this;
    }

    public function getExecutionDueDate(): ?\DateTimeImmutable
    {
        return $this->executionDueDate;
    }

    public function setExecutionDueDate(?\DateTimeImmutable $executionDueDate): static
    {
        $this->executionDueDate = $executionDueDate;

        return $this;
    }

    public function getBeneficiaryName(): ?string
    {
        return $this->beneficiaryName;
    }

    public function setBeneficiaryName(?string $beneficiaryName): static
    {
        $this->beneficiaryName = $beneficiaryName;

        return $this;
    }

    public function getBeneficiaryBankName(): ?string
    {
        return $this->beneficiaryBankName;
    }

    public function setBeneficiaryBankName(?string $beneficiaryBankName): static
    {
        $this->beneficiaryBankName = $beneficiaryBankName;

        return $this;
    }

    public function getBeneficiarySwift(): ?string
    {
        return $this->beneficiarySwift;
    }

    public function setBeneficiarySwift(?string $beneficiarySwift): static
    {
        $this->beneficiarySwift = $beneficiarySwift;

        return $this;
    }

    public function getBeneficiaryAccount(): ?string
    {
        return $this->beneficiaryAccount;
    }

    public function setBeneficiaryAccount(?string $beneficiaryAccount): static
    {
        $this->beneficiaryAccount = $beneficiaryAccount;

        return $this;
    }

    public function getBeneficiaryRawDetails(): ?string
    {
        return $this->beneficiaryRawDetails;
    }

    public function setBeneficiaryRawDetails(?string $beneficiaryRawDetails): static
    {
        $this->beneficiaryRawDetails = $beneficiaryRawDetails;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getSourceDocument(): ?Document
    {
        return $this->sourceDocument;
    }

    public function setSourceDocument(?Document $sourceDocument): static
    {
        $this->sourceDocument = $sourceDocument;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getConfirmedBy(): ?User
    {
        return $this->confirmedBy;
    }

    public function setConfirmedBy(?User $confirmedBy): static
    {
        $this->confirmedBy = $confirmedBy;

        return $this;
    }
}
