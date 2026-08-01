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
        $errors = [];

        $this->validateCbrRate($fields, $errors);
        $this->validatePaymentAmountRub($fields, $errors);
        $this->validateAgencyFeeAmountRub($fields, $errors);
        $this->validateTotalAmountRub($fields, $errors);

        return $errors;
    }

    /**
     * Если в документе есть курс, сверяем его с ЦБ РФ на дату заявки.
     * Если курс не совпал, это предупреждение для оператора, но дальше
     * расчеты все равно делаем по курсу из документа.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validateCbrRate(array $fields, array &$errors): void
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

        if (!$this->equals($documentRate, $cbrRate, 4)) {
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
    private function validatePaymentAmountRub(array $fields, array &$errors): void
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

        if (!$this->moneyEquals($calculatedPaymentAmountRub, $paymentAmountRub)) {
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
    private function validateAgencyFeeAmountRub(array $fields, array &$errors): void
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

        if (!$this->moneyEquals($calculatedAgencyFeeAmountRub, $agencyFeeAmountRub)) {
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
    private function validateTotalAmountRub(array $fields, array &$errors): void
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

        if (!$this->moneyEquals($calculatedTotalAmountRub, $totalAmountRub)) {
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
}
