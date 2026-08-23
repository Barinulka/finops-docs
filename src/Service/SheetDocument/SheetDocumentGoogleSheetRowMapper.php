<?php

namespace App\Service\SheetDocument;

use App\Entity\SheetDocument;
use App\Service\Geo\CountryNameLocalizer;

final readonly class SheetDocumentGoogleSheetRowMapper
{
    public function __construct(
        private CountryNameLocalizer $countryNameLocalizer,
    ) {
    }

    /**
     * Возвращает только те колонки, которые мы имеем право заполнять.
     * Формульные и автоматические колонки сюда не добавляем.
     *
     * @return array<string, string|int|float|bool>
     */
    public function map(SheetDocument $sheetDocument): array
    {
        $fields = $sheetDocument->getParsedFields();

        return array_filter([
            'D' => $fields['requestDate'] ?? null,
            'E' => $this->buildRequestNumber($fields),
            'F' => $this->resolveClientName($fields),
            'G' => $this->decimalToFloat($fields['paymentAmount'] ?? null),
            'H' => $fields['paymentCurrency'] ?? null,
            'I' => $fields['beneficiaryName'] ?? null,
            'J' => $this->countryNameLocalizer->toRussian($fields['beneficiaryCountry'] ?? null),
            'K' => $this->resolveExecutionBusinessDays($fields),
            'L' => $this->formatPercent($this->resolveAgencyFeePercent($fields)),
//            'M' => $this->resolveExecutionDueDate($fields),
            'N' => 'Предоплата',
            'O' => $this->resolveExtraPaymentAmount($fields),
            'P' => $this->resolveExtraPaymentCurrency($fields),
            'Q' => 'RUB',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function buildDocumentNumber(array $fields): ?string
    {
        $requestNumber = $fields['requestNumber'] ?? null;

        if ($requestNumber === null || $requestNumber === '') {
            return null;
        }

        return sprintf('Заявка %s', (string) $requestNumber);
    }

    private function buildComment(SheetDocument $sheetDocument): ?string
    {
        $fields = $sheetDocument->getParsedFields();
        $parts = [];

        if (($fields['termsComment'] ?? null) !== null && $fields['termsComment'] !== '') {
            $parts[] = (string) $fields['termsComment'];
        }

        $validationErrors = $sheetDocument->getValidationErrors();

        if ($validationErrors !== []) {
            $parts[] = sprintf("Проблемы проверки:\n- %s", implode("\n- ", $validationErrors));
        }

        if ($sheetDocument->getOriginalFilename()) {
            $parts[] = sprintf('Файл: %s', $sheetDocument->getOriginalFilename());
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveExecutionBusinessDays(array $fields): ?int
    {
        if (($fields['executionBusinessDays'] ?? null) !== null && $fields['executionBusinessDays'] !== '') {
            return (int) $fields['executionBusinessDays'];
        }
        $businessDays = $this->extractBusinessDays($fields['executionTermRaw'] ?? null);

        if ($businessDays !== null) {
            return $businessDays;
        }

        return $this->countBusinessDaysBetween(
            $fields['requestDate'] ?? null,
            $fields['executionDueDate'] ?? null,
        );
    }

    private function countBusinessDaysBetween(mixed $startDate, mixed $endDate): ?int
    {
        $start = $this->dateFromValue($startDate);
        $end = $this->dateFromValue($endDate);

        if ($start === null || $end === null) {
            return null;
        }

        if ($end < $start) {
            return null;
        }

        $days = 0;
        $current = $start->modify('+1 day');

        while ($current <= $end) {
            if ((int) $current->format('N') <= 5) {
                ++$days;
            }

            $current = $current->modify('+1 day');
        }

        return $days;
    }

    private function dateFromValue(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);

            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private function extractBusinessDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;

        if (preg_match('/(\d+)\s*\([^)]*\)\s*(?:рабочего|рабочих|working|business)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(\d+)\s*(?:рабочего|рабочих|раб\.?|working|business)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(\d+)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function mapPaymentType(mixed $paymentType, mixed $paymentTypeRaw): ?string
    {
        $value = strtolower((string) ($paymentType ?: $paymentTypeRaw));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'prepayment') || str_contains($value, 'предоплат')) {
            return 'Предоплата';
        }

        if (str_contains($value, 'postpayment') || str_contains($value, 'постоплат')) {
            return 'Постоплата';
        }

        if (str_contains($value, 'term') || str_contains($value, 'within') || str_contains($value, 'течение')) {
            return 'В срок';
        }

        return (string) ($paymentTypeRaw ?: $paymentType);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveAgencyFeePercent(array $fields): mixed
    {
        if (($fields['agencyFeePercent'] ?? null) !== null && $fields['agencyFeePercent'] !== '') {
            return $fields['agencyFeePercent'];
        }

        $paymentAmount = $this->decimalToFloat($fields['paymentAmount'] ?? null);
        $exchangeRate = $this->decimalToFloat($fields['exchangeRate'] ?? null);
        $agencyFeeRub = $this->decimalToFloat($fields['agencyFeeAmountRub'] ?? null);

        if ($paymentAmount === null || $paymentAmount <= 0.0) {
            return null;
        }

        if ($exchangeRate === null || $exchangeRate <= 0.0) {
            return null;
        }

        if ($agencyFeeRub === null || $agencyFeeRub <= 0.0) {
            return null;
        }

        $agencyFeeInCurrency = $agencyFeeRub / $exchangeRate;

        return round(($agencyFeeInCurrency / $paymentAmount) * 100, 4);
    }

    private function formatPercent(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return sprintf('%s%%', str_replace('.', ',', rtrim(rtrim((string) $value, '0'), '.')));
    }

    private function decimalToFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(
            [' ', "\u{00A0}", ','],
            ['', '', '.'],
            (string) $value,
        );

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function buildRequestNumber(array $fields): ?string
    {
        $requestNumber = $fields['requestNumber'] ?? null;

        if ($requestNumber === null || $requestNumber === '') {
            return null;
        }

        return (string) $requestNumber;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveClientName(array $fields): ?string
    {
        if (($fields['clientName'] ?? null) !== null && $fields['clientName'] !== '') {
            return (string) $fields['clientName'];
        }

        if ($this->isX5Contract($fields)) {
            return 'Х5';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveExecutionDueDate(array $fields): ?string
    {
        if (($fields['executionDueDate'] ?? null) !== null && $fields['executionDueDate'] !== '') {
            return (string) $fields['executionDueDate'];
        }

        $requestDate = $this->dateFromValue($fields['requestDate'] ?? null);
        $businessDays = $this->resolveExecutionBusinessDays($fields);

        if ($requestDate === null || $businessDays === null) {
            return null;
        }

        return $this->addBusinessDays($requestDate, $businessDays)->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveExtraPaymentAmount(array $fields): ?float
    {
        if ($this->isX5Contract($fields)) {
            return 0.0;
        }

        return $this->decimalToFloat($fields['extraPaymentAmount'] ?? null);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveExtraPaymentCurrency(array $fields): ?string
    {
        if ($this->isX5Contract($fields)) {
            return null;
        }

        return $fields['extraPaymentCurrency'] ?? null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function isX5Contract(array $fields): bool
    {
        return ($fields['contractNumber'] ?? null) === '6-2-100/018005-25';
    }

    private function addBusinessDays(\DateTimeImmutable $date, int $businessDays): \DateTimeImmutable
    {
        $current = $date;
        $added = 0;

        while ($added < $businessDays) {
            $current = $current->modify('+1 day');

            if ((int) $current->format('N') <= 5) {
                ++$added;
            }
        }

        return $current;
    }
}
