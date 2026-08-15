<?php

namespace App\Service\SheetDocument;

use App\Entity\SheetDocument;

final readonly class SheetDocumentGoogleSheetRowMapper
{
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
            'B' => $this->buildDocumentNumber($fields),
//            'D' => $this->buildComment($sheetDocument),
            'G' => $fields['clientName'] ?? null,
            'H' => $fields['beneficiaryName'] ?? null,
            'I' => $fields['beneficiaryCountry'] ?? null,
            'J' => $fields['requestDate'] ?? null,
            'K' => $this->resolveExecutionBusinessDays($fields),
            'L' => $this->mapPaymentType($fields['paymentType'] ?? null, $fields['paymentTypeRaw'] ?? null),
            'M' => $this->extractBusinessDays($fields['paymentTermRaw'] ?? null),
            'N' => $fields['paymentCurrency'] ?? null,
            'O' => $this->decimalToFloat($fields['paymentAmount'] ?? null),
            'P' => $this->formatPercent($this->resolveAgencyFeePercent($fields)),
            'Q' => $this->decimalToFloat($fields['extraPaymentAmount'] ?? null),
            'R' => $fields['extraPaymentCurrency'] ?? null,
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
}
