<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;

final readonly class TelegramDocumentGoogleSheetRowMapper
{
    /**
     * Превращает TelegramDocument в строку Google Sheets.
     *
     * Порядок колонок соответствует листу "Входящие заявки".
     *
     * @return list<string|int|float|bool|null>
     */
    public function map(TelegramDocument $telegramDocument): array
    {
        $fields = $telegramDocument->getParsedFields();

        return [
            null, // № п/п - таблица заполняет сама
            $this->buildDocumentNumber($fields), // Номер пп/заявки
            null, // Флаг недостаточности данных - таблица заполняет сама
            $this->buildComment($telegramDocument), // Комментарий
            null, // Флаг оплаты - таблица заполняет сама
            null, // Дата оплаты - факт оплаты из заявок не берем
            null, // Клиент - пока не парсим стабильно
            $fields['beneficiaryName'] ?? null, // Получатель
            null, // Страна получателя - пока не парсим стабильно
            $fields['requestDate'] ?? null, // Дата заявки
            $this->extractBusinessDays($fields['executionTermRaw'] ?? null), // Срок исполнения заявки
            $this->mapPaymentType($fields['paymentType'] ?? null, $fields['paymentTypeRaw'] ?? null), // Тип оплаты
            $this->extractBusinessDays($fields['paymentTermRaw'] ?? null), // Срок оплаты по заявке
            $fields['paymentCurrency'] ?? null, // Валюта заявки
            $fields['paymentAmount'] ?? null, // Сумма заявки в валюте
            $this->formatPercent($fields['agencyFeePercent'] ?? null), // СИК-СЕС %
            null, // Доп. платеж
            null, // Валюта доп. платежа
            $fields['paymentAmountRub'] ?? '0.00', // Сумма заявки в РУБ
            $fields['agencyFeeAmountRub'] ?? '0.00', // СИК-СЕС вознагражд-е в РУБ
            '0.00', // Доп. платеж в РУБ
            $fields['totalAmountRub'] ?? '0.00', // Общая сумма заявки + вознаграждения в РУБ
            $fields['totalAmountRub'] ?? '0.00', // Общая сумма фикс. в заявке в РУБ
            '0.00', // Вознаграждение клиента в РУБ
            null, // Котировка ЦБ заявки - таблица считает сама
            null, // Котировка ЦБ доп. расхода - таблица считает сама
        ];
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

    private function buildComment(TelegramDocument $telegramDocument): ?string
    {
        $fields = $telegramDocument->getParsedFields();
        $parts = [];

        if (($fields['termsComment'] ?? null) !== null && $fields['termsComment'] !== '') {
            $parts[] = (string) $fields['termsComment'];
        }

        $validationErrors = $telegramDocument->getValidationErrors();

        if ($validationErrors !== []) {
            $parts[] = sprintf("Проблемы проверки:\n- %s", implode("\n- ", $validationErrors));
        }

        if ($telegramDocument->getOriginalFilename()) {
            $parts[] = sprintf('Файл: %s', $telegramDocument->getOriginalFilename());
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
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

    /**
     * Возвращает только те колонки, которые мы имеем право заполнять.
     * Формульные и автоматические колонки сюда не добавляем.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function mapCells(TelegramDocument $telegramDocument): array
    {
        $fields = $telegramDocument->getParsedFields();

        return array_filter([
            'B' => $this->buildDocumentNumber($fields),
            'D' => null, // $this->buildComment($telegramDocument), // пока комментарий не пишем
            'G' => $fields['clientName'] ?? null,
            'H' => $fields['beneficiaryName'] ?? null,
            'I' => $fields['beneficiaryCountry'] ?? null,
            'J' => $fields['requestDate'] ?? null,
            'K' => $this->extractBusinessDays($fields['executionTermRaw'] ?? null),
            'L' => $this->mapPaymentType($fields['paymentType'] ?? null, $fields['paymentTypeRaw'] ?? null),
            'M' => $this->extractBusinessDays($fields['paymentTermRaw'] ?? null),
            'N' => $fields['paymentCurrency'] ?? null,
            'O' => $this->decimalToFloat($fields['paymentAmount'] ?? null),
            'P' => $this->formatPercent($this->resolveAgencyFeePercent($fields)),
            'Q' => $this->decimalToFloat($fields['extraPaymentAmount'] ?? null),
            'R' => $fields['extraPaymentCurrency'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
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
