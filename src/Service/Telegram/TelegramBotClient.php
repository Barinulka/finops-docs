<?php

namespace App\Service\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TelegramBotClient
{
    private const API_BASE_URL = 'https://api.telegram.org';
    private const FILE_BASE_URL = 'https://api.telegram.org/file';

    public function __construct(
        private TelegramBotConfig $config,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed>|null $replyMarkup
     *
     * @return array<string, mixed>
     */
    public function sendMessage(
        string|int $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?string $parseMode = null,
    ): array {
        $payload = [
            'chat_id' => (string) $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        /*
         * parse_mode нужен для форматирования сообщений.
         * По умолчанию не передаем, чтобы старые plain-text сообщения не поменяли поведение.
         */
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        /*
         * reply_markup - стандартный Telegram-механизм для inline-кнопок.
         * Если кнопок нет, параметр не отправляем вообще.
         */
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('sendMessage', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): array
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        /*
         * Если text передан, Telegram покажет короткое уведомление пользователю.
         */
        if ($text !== '') {
            $payload['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(string|int $chatId, string|int $messageId, ?array $replyMarkup = null): array
    {
        $payload = [
            'chat_id' => (string) $chatId,
            'message_id' => (int) $messageId,
        ];

        /*
         * null для reply_markup означает: удалить inline-кнопки у сообщения.
         */
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('editMessageReplyMarkup', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFile(string $fileId): array
    {
        return $this->request('getFile', [
            'file_id' => $fileId,
        ]);
    }

    public function downloadFile(string $filePath): string
    {
        $url = sprintf(
            '%s/bot%s/%s',
            self::FILE_BASE_URL,
            $this->config->botToken,
            ltrim($filePath, '/'),
        );

        return $this->httpClient->request('GET', $url)->getContent();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload): array
    {
        $url = sprintf(
            '%s/bot%s/%s',
            self::API_BASE_URL,
            $this->config->botToken,
            $method,
        );

        $response = $this->httpClient->request('POST', $url, [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        return $data;
    }
}
