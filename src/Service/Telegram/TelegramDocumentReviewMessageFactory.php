<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentStatus;

final readonly class TelegramDocumentReviewMessageFactory
{
    public function createText(TelegramDocument $telegramDocument): string
    {
        $fields = $telegramDocument->getParsedFields();
        $validationErrors = $telegramDocument->getValidationErrors();
        $requiresReview = in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::NeedsReview,
            TelegramDocumentStatus::ValidationFailed,
        ], true);

        /*
         * Это сообщение показывает пользователю, что именно будет записано.
         * Держим формат коротким: Telegram - не админка.
         */
        return implode("\n", array_filter([
            $requiresReview
                ? 'Документ распарсен, но требует проверки.'
                : 'Документ распарсен.',
            '',
            sprintf('Файл: %s', $telegramDocument->getOriginalFilename() ?? 'без названия'),
            sprintf('Номер: %s', $fields['requestNumber'] ?? 'не найден'),
            sprintf('Дата: %s', $fields['requestDate'] ?? 'не найдена'),
            sprintf('Договор: %s', $fields['contractNumber'] ?? 'не найден'),
            sprintf('Сумма: %s %s', $fields['paymentAmount'] ?? 'не найдена', $fields['paymentCurrency'] ?? ''),
            sprintf('Сумма RUB: %s', $fields['paymentAmountRub'] ?? $fields['totalAmountRub'] ?? 'не найдена'),
            sprintf('Курс: %s', $fields['exchangeRateRaw'] ?? 'нет'),
            sprintf('Вознаграждение: %s руб.', $fields['agencyFeeAmountRub'] ?? 'не найдено'),
            sprintf('Комментарий по срокам: %s', $fields['termsComment'] ?? 'нет'),
            ($fields['beneficiaryBank'] ?? null) !== null
                ? sprintf('Банк получателя: %s', $fields['beneficiaryBank'])
                : null,
            ($fields['invoiceNumber'] ?? null) !== null
                ? sprintf('Инвойс: %s', $fields['invoiceNumber'])
                : null,
            ($fields['invoiceDate'] ?? null) !== null
                ? sprintf('Дата инвойса: %s', $fields['invoiceDate'])
                : null,
            ($fields['beneficiaryName'] ?? null) !== null
                ? sprintf('Получатель: %s', $fields['beneficiaryName'])
                : null,
            ($fields['beneficiaryAccount'] ?? null) !== null
                ? sprintf('Счет получателя: %s', $fields['beneficiaryAccount'])
                : null,
            ($fields['swiftCode'] ?? null) !== null
                ? sprintf('SWIFT: %s', $fields['swiftCode'])
                : null,
            ($fields['paymentReference'] ?? null) !== null
                ? sprintf('Референс: %s', $fields['paymentReference'])
                : null,
            '',
            $validationErrors !== []
                ? sprintf("Проблемы проверки:\n- %s", implode("\n- ", $validationErrors))
                : null,
            $validationErrors !== []
                ? ''
                : null,
            $requiresReview
                ? 'Проверьте данные. Все равно записать в Google таблицу?'
                : 'Записать данные в Google таблицу?',
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function createReplyMarkup(TelegramDocument $telegramDocument): array
    {
        $id = (string) $telegramDocument->getId();

        $requiresReview = in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::NeedsReview,
            TelegramDocumentStatus::ValidationFailed,
        ], true);

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => $requiresReview
                            ? 'Все равно записать'
                            : 'Записать в таблицу',
                        'callback_data' => sprintf('write_to_sheet:%s', $id),
                    ],
                    [
                        'text' => 'Отклонить',
                        'callback_data' => sprintf('cancel_document:%s', $id),
                    ],
                ],
            ],
        ];
    }
}
