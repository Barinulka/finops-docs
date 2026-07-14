<?php

namespace App\Service\GoogleSheets;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

final readonly class GoogleSheetsClient
{
    public function __construct(
        private GoogleSheetsConfig $config,
    ) {
    }

    /**
     * Дописывает одну строку в конец указанного листа.
     *
     * @param list<string|int|float|bool|null> $row
     *
     * @return array<string, mixed>
     */
    public function appendRow(array $row): array
    {
        if ($row === []) {
            throw new \InvalidArgumentException('Google Sheets row must not be empty.');
        }

        /*
         * Google Sheets API плохо переносит null внутри values:
         * SDK может превратить PHP-массив в JSON-объект с ключами "0", "1", "2".
         * Поэтому null заменяем на пустую строку и переиндексируем массив.
         */
        $row = $this->normalizeRow($row);

        $client = new Client();
        $client->setApplicationName('CRM Telegram Sheets Writer');
        $client->setScopes([
            Sheets::SPREADSHEETS,
        ]);
        $client->setAuthConfig($this->config->credentialsPath);

        $service = new Sheets($client);

        $body = new ValueRange([
            'values' => [
                $row,
            ],
        ]);

        $response = $service->spreadsheets_values->append(
            $this->config->spreadsheetId,
            $this->config->sheetName,
            $body,
            [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ],
        );

        $responseData = $response->toSimpleObject();

        if ($responseData === null) {
            return [];
        }

        return json_decode(
            json_encode($responseData, JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param list<string|int|float|bool|null> $row
     *
     * @return list<string|int|float|bool>
     */
    private function normalizeRow(array $row): array
    {
        return array_values(array_map(
            static fn (string|int|float|bool|null $value): string|int|float|bool => $value ?? '',
            $row,
        ));
    }
}
