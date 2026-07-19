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

        $lines = [
            $requiresReview
                ? '<b>Документ распарсен, но требует проверки</b>'
                : '<b>Документ распарсен</b>',
            '',
            '<b>Данные</b>',
            sprintf('Файл: %s', $this->escape($telegramDocument->getOriginalFilename() ?? 'без названия')),
            sprintf('Номер: %s', $this->escape($fields['requestNumber'] ?? 'не найден')),
            sprintf('Дата: %s', $this->escape($fields['requestDate'] ?? 'не найдена')),
            sprintf('Договор: %s', $this->escape($fields['contractNumber'] ?? 'не найден')),
            sprintf(
                'Сумма: %s %s',
                $this->escape($fields['paymentAmount'] ?? 'не найдена'),
                $this->escape($fields['paymentCurrency'] ?? ''),
            ),
            sprintf('Сумма RUB: %s', $this->escape($fields['paymentAmountRub'] ?? $fields['totalAmountRub'] ?? 'не найдена')),
            sprintf('Курс: %s', $this->escape($fields['exchangeRateRaw'] ?? 'нет')),
            sprintf('Вознаграждение: %s руб.', $this->escape($fields['agencyFeeAmountRub'] ?? 'не найдено')),
            sprintf('Комментарий по срокам: %s', $this->escape($fields['termsComment'] ?? 'нет')),
        ];

        $this->appendOptionalLine($lines, 'Банк получателя', $fields['beneficiaryBank'] ?? null);
        $this->appendOptionalLine($lines, 'Инвойс', $fields['invoiceNumber'] ?? null);
        $this->appendOptionalLine($lines, 'Дата инвойса', $fields['invoiceDate'] ?? null);
        $this->appendOptionalLine($lines, 'Получатель', $fields['beneficiaryName'] ?? null);
        $this->appendOptionalLine($lines, 'Счет получателя', $fields['beneficiaryAccount'] ?? null);
        $this->appendOptionalLine($lines, 'SWIFT', $fields['swiftCode'] ?? null);
        $this->appendOptionalLine($lines, 'Референс', $fields['paymentReference'] ?? null);

        if ($validationErrors !== []) {
            $lines[] = '';
            $lines[] = '<b>Проблемы проверки</b>';

            foreach ($validationErrors as $error) {
                $lines[] = sprintf('- %s', $this->escape($error));
            }
        }

        $lines[] = '';
        $lines[] = '<b>Действие</b>';
        $lines[] = $requiresReview
            ? 'Проверьте данные. Все равно записать в Google таблицу?'
            : 'Записать данные в Google таблицу?';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     */
    private function appendOptionalLine(array &$lines, string $label, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $lines[] = sprintf('%s: %s', $label, $this->escape($value));
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
