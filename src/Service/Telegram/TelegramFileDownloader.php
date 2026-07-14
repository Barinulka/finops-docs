<?php

namespace App\Service\Telegram;

final readonly class TelegramFileDownloader
{
    public function __construct(
        private TelegramBotClient $telegramBotClient,
    ) {
    }

    public function download(string $fileId): TelegramDownloadedFile
    {
        // 1. Telegram getFile возвращает служебную информацию о файле.
        // Нам нужен result.file_path, потому что именно по нему скачивается файл.
        $fileInfo = $this->telegramBotClient->getFile($fileId);

        $result = $fileInfo['result'] ?? null;

        if (!is_array($result)) {
            throw new \RuntimeException('Telegram getFile response does not contain result.');
        }

        $filePath = $result['file_path'] ?? null;

        if (!is_string($filePath) || $filePath === '') {
            throw new \RuntimeException('Telegram getFile response does not contain file_path.');
        }

        // 2. Скачиваем бинарное содержимое файла.
        // Это может быть PDF, поэтому не пытаемся работать с ним как с текстом.
        $contents = $this->telegramBotClient->downloadFile($filePath);

        // 3. Защита от странного пустого ответа.
        if ($contents === '') {
            throw new \RuntimeException('Downloaded Telegram file is empty.');
        }

        // 4. sha256 нужен для дедупликации по содержимому.
        // file_unique_id хорош для Telegram, но checksum нужен уже на уровне нашей системы.
        $checksumSha256 = hash('sha256', $contents);

        // 5. strlen для бинарной строки возвращает размер в байтах.
        $sizeBytes = strlen($contents);

        return new TelegramDownloadedFile(
            contents: $contents,
            checksumSha256: $checksumSha256,
            sizeBytes: $sizeBytes,
        );
    }
}
