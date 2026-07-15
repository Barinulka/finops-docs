<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Service\Currency\CbrCurrencyRateProvider;

final readonly class TelegramDocumentBusinessValidator
{
    public function __construct(
        private CbrCurrencyRateProvider $currencyRateProvider,
    ) {
    }

    public function validate(TelegramDocument $telegramDocument): TelegramDocumentValidationResult
    {
        if (!$this->supports($telegramDocument)) {
            return new TelegramDocumentValidationResult(true);
        }

        $fields = $telegramDocument->getParsedFields();
        $errors = [];

        if ($errors !== []) {
            return new TelegramDocumentValidationResult(false, $errors);
        }

        $requestDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $fields['requestDate']);

        if (!$requestDate instanceof \DateTimeImmutable) {
            return new TelegramDocumentValidationResult(false, [
                sprintf('Некорректная дата заявки: %s.', (string) $fields['requestDate']),
            ]);
        }

        $currency = (string) $fields['paymentCurrency'];

        try {
            $cbrRate = $this->currencyRateProvider->getRateForDate($currency, $requestDate);
        } catch (\Throwable $exception) {
            return new TelegramDocumentValidationResult(false, [
                sprintf('Не удалось получить курс ЦБ РФ: %s', $exception->getMessage()),
            ]);
        }

        $documentRate = $this->toScale((string) $fields['exchangeRate'], 8);

        if (!$this->equals($documentRate, $cbrRate, 4)) {
            $errors[] = sprintf(
                'Курс в документе не совпадает с курсом ЦБ РФ на %s. В документе: %s, ЦБ РФ: %s.',
                $requestDate->format('d.m.Y'),
                $this->formatDecimal($documentRate),
                $this->formatDecimal($cbrRate),
            );
        }

        $paymentAmount = $this->toScale((string) $fields['paymentAmount'], 8);
        $paymentAmountRub = $this->toScale((string) $fields['paymentAmountRub'], 2);
        $agencyFeePercent = $this->toScale((string) $fields['agencyFeePercent'], 8);
        $agencyFeeAmountRub = $this->toScale((string) $fields['agencyFeeAmountRub'], 2);
        $totalAmountRub = $this->toScale((string) $fields['totalAmountRub'], 2);

        $calculatedPaymentAmountRub = $this->roundMoney(bcmul($paymentAmount, $documentRate, 8));

        if (!$this->equals($calculatedPaymentAmountRub, $paymentAmountRub, 2)) {
            $errors[] = sprintf(
                'Оплата по инвойсу в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedPaymentAmountRub),
                $this->formatDecimal($paymentAmountRub),
            );
        }

        $calculatedAgencyFeeAmountRub = $this->roundMoney(
            bcdiv(bcmul($paymentAmountRub, $agencyFeePercent, 8), '100', 8),
        );

        if (!$this->equals($calculatedAgencyFeeAmountRub, $agencyFeeAmountRub, 2)) {
            $errors[] = sprintf(
                'Агентское вознаграждение в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedAgencyFeeAmountRub),
                $this->formatDecimal($agencyFeeAmountRub),
            );
        }

        $calculatedTotalAmountRub = $this->roundMoney(bcadd($paymentAmountRub, $agencyFeeAmountRub, 8));

        if (!$this->equals($calculatedTotalAmountRub, $totalAmountRub, 2)) {
            $errors[] = sprintf(
                'Общая сумма в рублях не сходится. Ожидалось: %s, в документе: %s.',
                $this->formatDecimal($calculatedTotalAmountRub),
                $this->formatDecimal($totalAmountRub),
            );
        }

        return new TelegramDocumentValidationResult($errors === [], $errors);
    }

    private function supports(TelegramDocument $telegramDocument): bool
    {
        $rawText = mb_strtolower($telegramDocument->getRawText() ?? '');

        /*
         * Сейчас бизнес-проверку включаем только для нового шаблона заявки,
         * где явно есть курс, комиссия агента и сумма оплаты по инвойсу.
         *
         * Это не просто application_form: старые заявки тоже имеют такой documentType,
         * но не обязаны проходить эту финансовую сверку.
         */
        return str_contains($rawText, 'курс валюты:')
            && str_contains($rawText, 'комиссия агента')
            && str_contains($rawText, 'оплата по инвойсу составляет')
            && str_contains($rawText, 'агентское вознаграждение составляет');
    }

    private function toScale(string $value, int $scale): string
    {
        $normalized = str_replace(',', '.', trim($value));

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
}
