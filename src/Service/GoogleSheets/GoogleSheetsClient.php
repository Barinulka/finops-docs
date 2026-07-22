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
     * Дописывает строку, заполняя только конкретные колонки.
     * Остальные колонки строки не отправляются в Google API и не затирают формулы.
     *
     * @param array<string, string|int|float|bool|null> $cellsByColumn
     *
     * @return array<string, mixed>
     */
    public function appendSparseRow(array $cellsByColumn): array
    {
        $cellsByColumn = array_filter(
            $cellsByColumn,
            static fn (string|int|float|bool|null $value): bool => $value !== null && $value !== '',
        );

        if ($cellsByColumn === []) {
            throw new \InvalidArgumentException('Google Sheets cells must not be empty.');
        }

        $client = $this->createClient();
        $service = new Sheets($client);

        $nextRowNumber = $this->resolveNextRowNumber($service);

        $data = [];

        foreach ($cellsByColumn as $column => $value) {
            $data[] = new ValueRange([
                'range' => sprintf('%s!%s%d', $this->quoteSheetName(), $column, $nextRowNumber),
                'values' => [
                    [$value],
                ],
            ]);
        }

        $body = new Sheets\BatchUpdateValuesRequest([
            'valueInputOption' => 'USER_ENTERED',
            'data' => $data,
        ]);

        $response = $service->spreadsheets_values->batchUpdate(
            $this->config->spreadsheetId,
            $body,
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

    private function createClient(): Client
    {
        $client = new Client();
        $client->setApplicationName('CRM Telegram Sheets Writer');
        $client->setScopes([
            Sheets::SPREADSHEETS,
        ]);
        $client->setAuthConfig($this->config->credentialsPath);

        return $client;
    }

    private function resolveNextRowNumber(Sheets $service): int
    {
        $response = $service->spreadsheets_values->get(
            $this->config->spreadsheetId,
            sprintf('%s!B:B', $this->quoteSheetName()),
        );

        $values = $response->getValues();

        if ($values === null || $values === []) {
            return 1;
        }

        return count($values) + 1;
    }

    private function quoteSheetName(): string
    {
        return sprintf("'%s'", str_replace("'", "''", $this->config->sheetName));
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
