<?php

namespace App\Service\Operation;

use App\Entity\Operation;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\OperationStatus;
use App\Service\Audit\AuditLogger;
use App\Service\Operation\Exception\OperationConfirmationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OperationConfirmer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * @throws OperationConfirmationException
     */
    public function confirm(Operation $operation, User $actor): Operation
    {
        if ($operation->getStatus() === OperationStatus::Confirmed) {
            return $operation;
        }

        $this->validateBeforeConfirmation($operation);

        $now = new \DateTimeImmutable();

        $operation
            ->setStatus(OperationStatus::Confirmed)
            ->setConfirmedAt($now)
            ->setConfirmedBy($actor);

        $document = $operation->getSourceDocument();

        if ($document !== null) {
            $document
                ->setStatus(DocumentStatus::Confirmed)
                ->setConfirmedAt($now);
        }

        $this->auditLogger->confirmed('operation', (string) $operation->getId(), [
            'documentId' => $document?->getId() !== null ? (string) $document->getId() : null,
            'clientId' => $operation->getClient()?->getId() !== null ? (string) $operation->getClient()->getId() : null,
        ]);

        $this->entityManager->flush();

        return $operation;
    }

    private function validateBeforeConfirmation(Operation $operation): void
    {
        if ($operation->getSourceDocument() === null) {
            throw new OperationConfirmationException('Нельзя подтвердить операцию без исходного документа.');
        }

        $this->assertPositiveDecimal($operation->getPaymentAmount(), 'Сумма платежа');
        $this->assertCurrency($operation->getPaymentCurrency());
        $this->assertExchangeRate($operation);
        $this->assertNonNegativeDecimal($operation->getAgencyFeeAmountRub(), 'Агентское вознаграждение, руб.');
        $this->assertPositiveDecimal($operation->getTotalAmountRub(), 'Общая сумма, руб.');

        if ($this->isBlank($operation->getExecutionTermRaw())) {
            throw new OperationConfirmationException('Заполните срок выполнения.');
        }
    }

    private function assertCurrency(?string $value): void
    {
        if ($this->isBlank($value)) {
            throw new OperationConfirmationException('Заполните валюту платежа.');
        }

        if (!preg_match('/^[A-Z]{3}$/', strtoupper((string) $value))) {
            throw new OperationConfirmationException('Валюта платежа должна быть ISO-кодом из 3 букв.');
        }
    }

    private function assertExchangeRate(Operation $operation): void
    {
        $exchangeRate = $operation->getExchangeRate();
        $exchangeRateRaw = mb_strtolower(trim((string) $operation->getExchangeRateRaw()));

        if (!$this->isBlank($exchangeRate)) {
            $this->assertPositiveDecimal($exchangeRate, 'Обменный курс');

            return;
        }

        if ($exchangeRateRaw !== 'нет') {
            throw new OperationConfirmationException('Укажите обменный курс или значение "нет".');
        }
    }

    private function assertPositiveDecimal(?string $value, string $fieldName): void
    {
        if ($this->isBlank($value)) {
            throw new OperationConfirmationException(sprintf('Заполните поле "%s".', $fieldName));
        }

        if (!is_numeric($value) || bccomp((string) $value, '0', 8) <= 0) {
            throw new OperationConfirmationException(sprintf('Поле "%s" должно быть больше 0.', $fieldName));
        }
    }

    private function assertNonNegativeDecimal(?string $value, string $fieldName): void
    {
        if ($this->isBlank($value)) {
            throw new OperationConfirmationException(sprintf('Заполните поле "%s".', $fieldName));
        }

        if (!is_numeric($value) || bccomp((string) $value, '0', 8) < 0) {
            throw new OperationConfirmationException(sprintf('Поле "%s" не может быть меньше 0.', $fieldName));
        }
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
