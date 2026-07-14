<?php

namespace App\Service\GoogleSheets;

final readonly class GoogleSheetsConfig
{
    public function __construct(
        public string $spreadsheetId,
        public string $sheetName,
        public string $credentialsPath,
    ) {
        /*
         * Проверяем конфигурацию сразу при создании сервиса.
         * Так ошибка будет понятной: не "Google API упал где-то внутри",
         * а конкретно "не указан spreadsheet id" или "нет json-ключа".
         */
        if ($this->spreadsheetId === '') {
            throw new \InvalidArgumentException('Google Sheets spreadsheet id is not configured.');
        }

        if ($this->sheetName === '') {
            throw new \InvalidArgumentException('Google Sheets sheet name is not configured.');
        }

        if ($this->credentialsPath === '') {
            throw new \InvalidArgumentException('Google service account credentials path is not configured.');
        }
    }
}
