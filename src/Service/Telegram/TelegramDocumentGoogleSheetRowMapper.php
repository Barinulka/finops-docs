<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;

final readonly class TelegramDocumentGoogleSheetRowMapper
{
    /**
     * Превращает TelegramDocument в строку Google Sheets.
     *
     * Здесь специально собран порядок колонок.
     * Если заказчик поменяет структуру таблицы, менять нужно этот класс,
     * а не GoogleSheetsClient и не writer.
     *
     * @return list<string|int|float|bool|null>
     */
    public function map(TelegramDocument $telegramDocument): array
    {
        $fields = $telegramDocument->getParsedFields();

        return [
            $telegramDocument->getCreatedAt()?->format('Y-m-d H:i:s'),
            (string) $telegramDocument->getId(),
            $telegramDocument->getOriginalFilename(),

            $fields['requestNumber'] ?? null,
            $fields['requestDate'] ?? null,
            $fields['contractNumber'] ?? null,
            $fields['contractDate'] ?? null,

            $fields['paymentAmount'] ?? null,
            $fields['paymentCurrency'] ?? null,
            $fields['paymentAmountRub'] ?? null,

            $fields['exchangeRateRaw'] ?? null,
            $fields['agencyFeePercent'] ?? null,
            $fields['agencyFeeAmountRub'] ?? null,
            $fields['totalAmountRub'] ?? null,

            /*
             * Сроки пока кладем одним комментарием, как попросил заказчик.
             * При этом raw-поля остаются в parsedFields, если позже захотим
             * разнести их по отдельным колонкам.
             */
            $fields['termsComment'] ?? null,

            $telegramDocument->getParserConfidence(),
            $telegramDocument->getStatus()->label(),
        ];
    }
}
