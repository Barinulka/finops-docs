<?php

namespace App\Service\DocumentParsing;

use App\Service\Currency\CbrCurrencyRateProvider;

final readonly class ParsedFieldsBusinessValidator
{
    public function __construct(
        private CbrCurrencyRateProvider $currencyRateProvider,
    ) {
    }

    /**
     * Проверяет уже распаршенные поля документа.
     *
     * Важно: проверяем только те значения, которые реально есть в документе.
     * Если какого-то поля нет, проверку по нему пропускаем.
     *
     * @param array<string, mixed> $fields
     *
     * @return list<string>
     */
    public function validate(array $fields): array
    {
        return $this->validateWithDetails($fields)->errors;
    }

    /**
     * Проверяет распаршенные поля и возвращает не только ошибки,
     * но и подробности расчетов для показа оператору.
     *
     * @param array<string, mixed> $fields
     */
    public function validateWithDetails(array $fields): ParsedFieldsValidationResult
    {
        $errors = [];
        $details = [];

        $this->validateCbrRate($fields, $errors, $details);
        $this->validatePaymentAmountRub($fields, $errors, $details);
        $this->validateAgencyFeeAmountRub($fields, $errors, $details);
        $this->validateTotalAmountRub($fields, $errors, $details);
        $this->validateExecutionBusinessDays($fields, $details);

        return new ParsedFieldsValidationResult($errors, $details);
    }

    /**
     * Если в документе есть курс, сверяем его с ЦБ РФ на дату заявки.
     * Если курс не совпал, это предупреждение для оператора, но дальше
     * расчеты все равно делаем по курсу из документа.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validateCbrRate(array $fields, array &$errors, array &$details): void
    {
        if (!$this->hasFields($fields, ['requestDate', 'paymentCurrency', 'exchangeRate'])) {
            return;
        }

        $requestDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fields['requestDate']);

        if (!$requestDate instanceof \DateTimeImmutable) {
            $errors[] = sprintf('Некорректная дата заявки: %s.', (string) $fields['requestDate']);

            return;
        }

        $documentRate = $this->decimalField($fields, 'exchangeRate', 'Курс валюты', 8, $errors);

        if ($documentRate === null) {
            return;
        }

        try {
            $cbrRate = $this->currencyRateProvider->getRateForDate(
                (string) $fields['paymentCurrency'],
                $requestDate,
            );
        } catch (\Throwable $exception) {
            $errors[] = sprintf('Не удалось получить курс ЦБ РФ: %s', $exception->getMessage());

            return;
        }

        $passed = $this->equals($documentRate, $cbrRate, 4);

        $details[] = [
            'title' => 'Курс валюты по ЦБ РФ',
            'status' => $passed ? 'passed' : 'failed',
            'formula' => sprintf(
                'Курс %s на дату %s',
                (string) $fields['paymentCurrency'],
                $requestDate->format('d.m.Y'),
            ),
            'expected' => $this->formatDecimal($cbrRate),
            'actual' => $this->formatDecimal($documentRate),
            'message' => $passed
                ? 'Курс в документе совпадает с курсом ЦБ РФ.'
                : 'Курс в документе не совпадает с курсом ЦБ РФ. Денежные проверки ниже считаются по курсу из документа.',
        ];

        if (!$passed) {
            $errors[] = sprintf(
                'Курс в документе не совпадает с курсом ЦБ РФ на %s. В документе: %s, ЦБ РФ: %s.',
                $requestDate->format('d.m.Y'),
                $this->formatDecimal($documentRate),
                $this->formatDecimal($cbrRate),
            );
        }
    }

    /**
     * Проверяем рублевую сумму заявки: сумма в валюте * курс из документа.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validatePaymentAmountRub(array $fields, array &$errors, array &$details): void
    {
        if (!$this->hasFields($fields, ['paymentAmount', 'exchangeRate', 'paymentAmountRub'])) {
            return;
        }

        $paymentAmount = $this->decimalField($fields, 'paymentAmount', 'Сумма платежа в валюте', 8, $errors);
        $exchangeRate = $this->decimalField($fields, 'exchangeRate', 'Курс валюты', 8, $errors);
        $paymentAmountRub = $this->decimalField($fields, 'paymentAmountRub', 'Сумма платежа в рублях', 2, $errors);

        if ($paymentAmount === null || $exchangeRate === null || $paymentAmountRub === null) {
            return;
        }

        $calculatedPaymentAmountRub = $this->roundMoney(bcmul($paymentAmount, $exchangeRate, 8));
        $passed = $this->moneyEquals($calculatedPaymentAmountRub, $paymentAmountRub);

        $details[] = [
            'title' => 'Сумма платежа в рублях',
            'status' => $passed ? 'passed' : 'failed',
            'formula' => sprintf(
                '%s * %s',
                $this->formatDecimal($paymentAmount),
                $this->formatDecimal($exchangeRate),
            ),
            'expected' => $this->formatDecimal($calculatedPaymentAmountRub),
            'actual' => $this->formatDecimal($paymentAmountRub),
            'message' => $passed
                ? 'Проверка пройдена.'
                : 'Сумма платежа в рублях не сходится.',
        ];

        if (!$passed) {
            $errors[] = sprintf(
                'Сумма платежа в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedPaymentAmountRub),
                $this->formatDecimal($paymentAmountRub),
            );
        }
    }

    /**
     * Проверяем вознаграждение.
     *
     * Сначала считаем вознаграждение в валюте и округляем до копеек/центов,
     * затем конвертируем в рубли. Такой порядок нужен для документов, где
     * вознаграждение считается сначала в валюте заявки.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validateAgencyFeeAmountRub(array $fields, array &$errors, array &$details): void
    {
        if (!$this->hasFields($fields, ['paymentAmount', 'exchangeRate', 'agencyFeePercent', 'agencyFeeAmountRub'])) {
            return;
        }

        $paymentAmount = $this->decimalField($fields, 'paymentAmount', 'Сумма платежа в валюте', 8, $errors);
        $exchangeRate = $this->decimalField($fields, 'exchangeRate', 'Курс валюты', 8, $errors);
        $agencyFeePercent = $this->decimalField($fields, 'agencyFeePercent', 'Процент вознаграждения', 8, $errors);
        $agencyFeeAmountRub = $this->decimalField($fields, 'agencyFeeAmountRub', 'Агентское вознаграждение', 2, $errors);

        if (
            $paymentAmount === null
            || $exchangeRate === null
            || $agencyFeePercent === null
            || $agencyFeeAmountRub === null
        ) {
            return;
        }

        $calculatedAgencyFeeAmount = $this->roundMoney(
            bcdiv(bcmul($paymentAmount, $agencyFeePercent, 8), '100', 8),
        );

        $calculatedAgencyFeeAmountRub = $this->roundMoney(
            bcmul($calculatedAgencyFeeAmount, $exchangeRate, 8),
        );

        $passed = $this->moneyEquals($calculatedAgencyFeeAmountRub, $agencyFeeAmountRub);

        $details[] = [
            'title' => 'Агентское вознаграждение в рублях',
            'status' => $passed ? 'passed' : 'failed',
            'formula' => sprintf(
                '(%s * %s / 100) = %s; %s * %s',
                $this->formatDecimal($paymentAmount),
                $this->formatDecimal($agencyFeePercent),
                $this->formatDecimal($calculatedAgencyFeeAmount),
                $this->formatDecimal($calculatedAgencyFeeAmount),
                $this->formatDecimal($exchangeRate),
            ),
            'expected' => $this->formatDecimal($calculatedAgencyFeeAmountRub),
            'actual' => $this->formatDecimal($agencyFeeAmountRub),
            'message' => $passed
                ? 'Проверка пройдена.'
                : 'Агентское вознаграждение в рублях не сходится.',
        ];

        if (!$passed) {
            $errors[] = sprintf(
                'Агентское вознаграждение в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedAgencyFeeAmountRub),
                $this->formatDecimal($agencyFeeAmountRub),
            );
        }
    }

    /**
     * Проверяем итог: сумма заявки в рублях + вознаграждение в рублях.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validateTotalAmountRub(array $fields, array &$errors, array &$details): void
    {
        if (!$this->hasFields($fields, ['paymentAmountRub', 'agencyFeeAmountRub', 'totalAmountRub'])) {
            return;
        }

        $paymentAmountRub = $this->decimalField($fields, 'paymentAmountRub', 'Сумма платежа в рублях', 2, $errors);
        $agencyFeeAmountRub = $this->decimalField($fields, 'agencyFeeAmountRub', 'Агентское вознаграждение', 2, $errors);
        $totalAmountRub = $this->decimalField($fields, 'totalAmountRub', 'Общая сумма в рублях', 2, $errors);

        if ($paymentAmountRub === null || $agencyFeeAmountRub === null || $totalAmountRub === null) {
            return;
        }

        $calculatedTotalAmountRub = $this->roundMoney(bcadd($paymentAmountRub, $agencyFeeAmountRub, 8));
        $passed = $this->moneyEquals($calculatedTotalAmountRub, $totalAmountRub);

        $details[] = [
            'title' => 'Общая сумма в рублях',
            'status' => $passed ? 'passed' : 'failed',
            'formula' => sprintf(
                '%s + %s',
                $this->formatDecimal($paymentAmountRub),
                $this->formatDecimal($agencyFeeAmountRub),
            ),
            'expected' => $this->formatDecimal($calculatedTotalAmountRub),
            'actual' => $this->formatDecimal($totalAmountRub),
            'message' => $passed
                ? 'Проверка пройдена.'
                : 'Общая сумма в рублях не сходится.',
        ];

        if (!$passed) {
            $errors[] = sprintf(
                'Общая сумма в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedTotalAmountRub),
                $this->formatDecimal($totalAmountRub),
            );
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $fieldNames
     */
    private function hasFields(array $fields, array $fieldNames): bool
    {
        foreach ($fieldNames as $fieldName) {
            if (!$this->hasValue($fields[$fieldName] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function hasValue(mixed $value): bool
    {
        return !in_array($value, [null, ''], true);
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function decimalField(array $fields, string $fieldName, string $label, int $scale, array &$errors): ?string
    {
        $rawValue = $fields[$fieldName] ?? null;

        if (!$this->hasValue($rawValue)) {
            return null;
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', (string) $rawValue);
        $normalized = str_replace(',', '.', trim($normalized));

        if (!is_numeric($normalized)) {
            $errors[] = sprintf('Поле "%s" содержит некорректное число: %s.', $label, (string) $rawValue);

            return null;
        }

        return bcadd($normalized, '0', $scale);
    }

    private function roundMoney(string $value): string
    {
        if (str_starts_with($value, '-')) {
            return bcsub($value, '0.005', 2);
        }

        return bcadd($value, '0.005', 2);
    }

    private function equals(string $left, string $right, int $scale): bool
    {
        return bccomp($left, $right, $scale) === 0;
    }

    private function formatDecimal(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }

    private function moneyEquals(string $left, string $right): bool
    {
        $diff = bcsub($left, $right, 2);

        if (str_starts_with($diff, '-')) {
            $diff = substr($diff, 1);
        }

        return bccomp($diff, '1.00', 2) <= 0;
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<array<string, string|null>> $details
     */
    private function validateExecutionBusinessDays(array $fields, array &$details): void
    {
        $executionTermRaw = $fields['executionTermRaw'] ?? null;
        $requestDate = $fields['requestDate'] ?? null;
        $executionDueDate = $fields['executionDueDate'] ?? null;

        $businessDays = $this->extractBusinessDays($executionTermRaw);

        if ($businessDays !== null) {
            $details[] = [
                'title' => 'Срок исполнения',
                'status' => 'passed',
                'formula' => sprintf('Из документа: %s', (string) $executionTermRaw),
                'expected' => (string) $businessDays,
                'actual' => (string) $businessDays,
                'message' => 'Срок исполнения указан числом рабочих дней.',
            ];

            return;
        }

        $calculatedBusinessDays = $this->countBusinessDaysBetween($requestDate, $executionDueDate);

        if ($calculatedBusinessDays === null) {
            return;
        }

        $details[] = [
            'title' => 'Срок исполнения',
            'status' => 'passed',
            'formula' => sprintf(
                'Рабочие дни между %s и %s, дата заявки не считается, дата исполнения считается',
                (string) $requestDate,
                (string) $executionDueDate,
            ),
            'expected' => (string) $calculatedBusinessDays,
            'actual' => (string) $calculatedBusinessDays,
            'message' => 'Срок исполнения рассчитан по дате заявки и дате исполнения.',
        ];
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
}
