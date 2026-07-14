<?php

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\Operation;
use App\Entity\ParsedDocument;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Enum\OperationStatus;
use App\Enum\OperationType;
use App\Repository\OperationRepository;
use App\Repository\ParsedDocumentRepository;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DocumentParsingResultApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParsedDocumentRepository $parsedDocumentRepository,
        private OperationRepository $operationRepository,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     */
    public function apply(Document $document, array $result, User $actor): Operation
    {
        $fields = $this->arrayValue($result['fields'] ?? [], 'fields');
        $warnings = $this->arrayValue($result['warnings'] ?? [], 'warnings');
        $documentType = $this->documentType($result['documentType'] ?? null);
        $parserVersion = $this->stringOrNull($result['parserVersion'] ?? null);

        $parsedDocument = $this->parsedDocumentRepository->findOneBy([
            'document' => $document,
        ]) ?? new ParsedDocument();

        $parsedDocument
            ->setDocument($document)
            ->setDocumentType($documentType)
            ->setConfidence($this->confidenceOrNull($result['confidence'] ?? null))
            ->setFields($fields)
            ->setRawPayload($result)
            ->setRawText($this->stringOrNull($result['rawText'] ?? null))
            ->setWarnings($warnings);

        $operation = $this->operationRepository->findOneBy([
            'sourceDocument' => $document,
        ]) ?? new Operation();

        $operation
            ->setClient($document->getClient())
            ->setSourceDocument($document)
            ->setStatus(OperationStatus::Draft)
            ->setType($this->operationType($fields))
            ->setCreatedBy($operation->getCreatedBy() ?? $actor)
            ->setPaymentAmount($this->decimalOrNull($fields['paymentAmount'] ?? null))
            ->setPaymentCurrency($this->currencyOrNull($fields['paymentCurrency'] ?? null))
            ->setExchangeRate($this->decimalOrNull($fields['exchangeRate'] ?? null))
            ->setExchangeRateRaw($this->exchangeRateRaw($fields['exchangeRateRaw'] ?? null, $fields['exchangeRate'] ?? null))
            ->setAgencyFeeAmountRub($this->decimalOrNull($fields['agencyFeeAmountRub'] ?? null))
            ->setTotalAmountRub($this->decimalOrNull($fields['totalAmountRub'] ?? null))
            ->setExecutionTermRaw($this->stringOrNull($fields['executionTermRaw'] ?? null))
            ->setExecutionDueDate($this->dateOrNull($fields['executionDueDate'] ?? null))
            ->setMetadata([
                'parserVersion' => $parserVersion,
                'documentType' => $documentType->value,
                'confidence' => $parsedDocument->getConfidence(),
                'warnings' => $warnings,
            ]);

        $document
            ->setType($documentType)
            ->setParserVersion($parserVersion)
            ->setParseError(null)
            ->setParsedAt(new \DateTimeImmutable())
            ->setStatus($warnings === [] ? DocumentStatus::Parsed : DocumentStatus::NeedsReview);

        $this->entityManager->persist($parsedDocument);
        $this->entityManager->persist($operation);

        $this->auditLogger->parsed('document', (string) $document->getId(), [
            'documentType' => $documentType->value,
            'parserVersion' => $parserVersion,
            'warnings' => $warnings,
        ]);

        $this->entityManager->flush();

        return $operation;
    }

    /**
     * @return array<mixed>
     */
    private function arrayValue(mixed $value, string $fieldName): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Parser result field "%s" must be an array.', $fieldName));
        }

        return $value;
    }

    private function documentType(mixed $value): DocumentType
    {
        if (!is_string($value) || trim($value) === '') {
            return DocumentType::Unknown;
        }

        return DocumentType::tryFrom(trim($value)) ?? DocumentType::Unknown;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function operationType(array $fields): OperationType
    {
        if (($fields['exchangeRate'] ?? null) !== null) {
            return OperationType::CurrencyConversion;
        }

        if (($fields['paymentAmount'] ?? null) !== null) {
            return OperationType::Payment;
        }

        return OperationType::Other;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('Expected scalar value.');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);

        if (!preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException(sprintf('Invalid decimal value "%s".', $value));
        }

        return $normalized;
    }

    private function currencyOrNull(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);

        if (!preg_match('/^[A-Z]{3}$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid currency value "%s".', $value));
        }

        return $value;
    }

    private function exchangeRateRaw(mixed $rawValue, mixed $normalizedValue): ?string
    {
        $rawValue = $this->stringOrNull($rawValue);

        if ($rawValue !== null) {
            return $rawValue;
        }

        return $normalizedValue === null ? 'нет' : null;
    }

    private function dateOrNull(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid date value "%s". Expected Y-m-d.', $value));
        }

        return $date;
    }

    private function confidenceOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Confidence must be numeric.');
        }

        $confidence = (float) $value;

        if ($confidence < 0 || $confidence > 1) {
            throw new \InvalidArgumentException('Confidence must be between 0 and 1.');
        }

        return $confidence;
    }
}
