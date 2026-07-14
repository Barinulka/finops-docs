<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramMessageLog;
use App\Entity\TelegramUser;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Enum\Telegram\TelegramDocumentSource;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Enum\Telegram\TelegramMessageDirection;
use App\Enum\Telegram\TelegramMessageStatus;
use App\Repository\TelegramDocumentRepository;
use App\Repository\TelegramUserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TelegramUpdateHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TelegramUserRepository $telegramUserRepository,
        private TelegramDocumentRepository $telegramDocumentRepository,
        private TelegramMessageSender $telegramMessageSender,
        private TelegramBotClient $telegramBotClient,
        private TelegramBotConfig $telegramBotConfig,
        private TelegramFileDownloader $telegramFileDownloader,
        private TelegramDocumentParser $telegramDocumentParser,
        private TelegramDocumentReviewMessageFactory $reviewMessageFactory,
        private TelegramDocumentGoogleSheetWriter $googleSheetWriter,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public function handle(array $update): void
    {
        /*
         * Telegram присылает разные типы update.
         * Сейчас нас интересуют:
         * - message: пользователь отправил PDF или текст;
         * - callback_query: пользователь нажал inline-кнопку.
         */
        $callbackQuery = $update['callback_query'] ?? null;

        if (is_array($callbackQuery)) {
            $this->handleCallbackQuery($callbackQuery, $update);

            return;
        }

        $message = $update['message'] ?? null;

        if (!is_array($message)) {
            return;
        }

        $this->handleMessage($message, $update);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $update
     */
    private function handleMessage(array $message, array $update): void
    {
        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;

        if (!is_array($from) || !is_array($chat)) {
            return;
        }

        $chatId = $chat['id'] ?? null;
        $telegramUserId = $from['id'] ?? null;

        if ($chatId === null || $telegramUserId === null) {
            return;
        }

        $document = $message['document'] ?? null;

        if ($document !== null && !is_array($document)) {
            return;
        }

        $log = $this->createIncomingMessageLog($chat, $message, $update);

        $telegramUser = $this->telegramUserRepository->findOneByTelegramId($telegramUserId);

        if ($telegramUser !== null) {
            $log->setTelegramUser($telegramUser);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        if (!$telegramUser || !$telegramUser->isActive()) {
            $this->telegramMessageSender->sendText(
                $chatId,
                sprintf(
                    "У вас нет доступа к этому боту.\nВаш Telegram ID: %s\nПередайте этот ID администратору.",
                    (string) $telegramUserId,
                ),
                $telegramUser,
            );

            return;
        }

        if ($document === null) {
            $this->telegramMessageSender->sendText(
                $chatId,
                'Отправьте PDF-файл.',
                $telegramUser,
            );

            return;
        }

        $mimeType = $document['mime_type'] ?? null;

        if ($mimeType !== 'application/pdf') {
            $this->telegramMessageSender->sendText(
                $chatId,
                'Файл должен быть в формате PDF.',
                $telegramUser,
            );

            return;
        }

        $fileSize = $document['file_size'] ?? null;

        if (!is_int($fileSize) || $fileSize > $this->telegramBotConfig->allowedMaxFileSize) {
            $this->telegramMessageSender->sendText(
                $chatId,
                'Файл слишком большой.',
                $telegramUser,
            );

            return;
        }

        $fileId = $document['file_id'] ?? null;
        $fileUniqueId = $document['file_unique_id'] ?? null;

        if (!is_string($fileId) || !is_string($fileUniqueId)) {
            $this->telegramMessageSender->sendText(
                $chatId,
                'Не удалось получить данные файла.',
                $telegramUser,
            );

            return;
        }

        $duplicateDocument = $this->telegramDocumentRepository->findOneByTelegramFileUniqueId($fileUniqueId);
        $telegramDocument = $this->createTelegramDocument($telegramUser, $message, $document);

        if ($duplicateDocument !== null) {
            $telegramDocument->setStatus(TelegramDocumentStatus::Duplicate);
            $telegramDocument->setDuplicateOf($duplicateDocument);

            $log->setTelegramDocument($telegramDocument);

            $this->entityManager->persist($telegramDocument);
            $this->entityManager->flush();

            $this->telegramMessageSender->sendText(
                $chatId,
                'Этот файл уже был загружен ранее.',
                $telegramUser,
                $telegramDocument,
            );

            return;
        }

        try {
            $downloadedFile = $this->telegramFileDownloader->download($fileId);

            $telegramDocument->setChecksumSha256($downloadedFile->checksumSha256);
            $telegramDocument->setSizeBytes($downloadedFile->sizeBytes);

            $this->telegramDocumentParser->parse($telegramDocument, $downloadedFile->contents);
        } catch (\Throwable $exception) {
            $telegramDocument->setStatus(TelegramDocumentStatus::Failed);
            $telegramDocument->setErrorMessage($exception->getMessage());
            $telegramDocument->setFailedAt(new \DateTimeImmutable());

            $log->setTelegramDocument($telegramDocument);

            $this->entityManager->persist($telegramDocument);
            $this->entityManager->flush();

            $this->telegramMessageSender->sendText(
                $chatId,
                'Не удалось скачать или обработать файл из Telegram.',
                $telegramUser,
                $telegramDocument,
            );

            return;
        }

        $log->setTelegramDocument($telegramDocument);

        $this->entityManager->persist($telegramDocument);
        $this->entityManager->flush();

        if (in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::Parsed,
            TelegramDocumentStatus::NeedsReview,
        ], true)) {
            $this->telegramMessageSender->sendText(
                $chatId,
                $this->reviewMessageFactory->createText($telegramDocument),
                $telegramUser,
                $telegramDocument,
                $this->reviewMessageFactory->createReplyMarkup($telegramDocument),
            );

            return;
        }

        $this->telegramMessageSender->sendText(
            $chatId,
            match ($telegramDocument->getStatus()) {
                TelegramDocumentStatus::Failed => 'Не удалось обработать файл. Проверьте PDF или отправьте другой документ.',
                default => 'Файл принят в обработку.',
            },
            $telegramUser,
            $telegramDocument,
        );
    }

    /**
     * @param array<string, mixed> $callbackQuery
     * @param array<string, mixed> $update
     */
    private function handleCallbackQuery(array $callbackQuery, array $update): void
    {
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $from = $callbackQuery['from'] ?? null;
        $message = $callbackQuery['message'] ?? null;
        $data = $callbackQuery['data'] ?? null;

        if (!is_string($callbackQueryId) || !is_array($from) || !is_array($message) || !is_string($data)) {
            return;
        }

        $chat = $message['chat'] ?? null;
        $chatId = is_array($chat) ? ($chat['id'] ?? null) : null;
        $telegramUserId = $from['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if ($chatId === null || $telegramUserId === null) {
            return;
        }

        $telegramUser = $this->telegramUserRepository->findOneByTelegramId($telegramUserId);

        if (!$telegramUser || !$telegramUser->isActive()) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Нет доступа.');

            return;
        }

        [$action, $telegramDocumentId] = $this->parseCallbackData($data);

        if ($action === null || $telegramDocumentId === null) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Неизвестное действие.');

            return;
        }

        $telegramDocument = $this->telegramDocumentRepository->find($telegramDocumentId);

        if ($telegramDocument === null) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Документ не найден.');

            return;
        }

        if ($telegramDocument->getTelegramUser()?->getId()?->__toString() !== $telegramUser->getId()?->__toString()) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Нет доступа к документу.');

            return;
        }

        if ($action === 'write_to_sheet') {
            $this->handleWriteToSheetCallback($callbackQueryId, $chatId, $messageId, $telegramUser, $telegramDocument);

            return;
        }

        if ($action === 'cancel_document') {
            $this->handleCancelDocumentCallback($callbackQueryId, $chatId, $messageId, $telegramUser, $telegramDocument);

            return;
        }

        $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Неизвестное действие.');
    }

    private function handleWriteToSheetCallback(
        string $callbackQueryId,
        string|int $chatId,
        string|int|null $messageId,
        TelegramUser $telegramUser,
        TelegramDocument $telegramDocument,
    ): void {
        if ($telegramDocument->getStatus() === TelegramDocumentStatus::Written) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Уже записано.');

            return;
        }

        if (!in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::Parsed,
            TelegramDocumentStatus::NeedsReview,
        ], true)) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Документ нельзя записать.');

            return;
        }

        $appendLog = $this->googleSheetWriter->write($telegramDocument);

        if ($appendLog->getStatus() === GoogleSheetAppendStatus::Success) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Записано.');
            $this->removeInlineKeyboard($chatId, $messageId);
            $this->telegramMessageSender->sendText(
                $chatId,
                'Данные записаны в Google таблицу.',
                $telegramUser,
                $telegramDocument,
            );

            return;
        }

        $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Ошибка записи.');
        $this->telegramMessageSender->sendText(
            $chatId,
            'Не удалось записать данные в Google таблицу. Ошибка сохранена в админке.',
            $telegramUser,
            $telegramDocument,
        );
    }

    private function handleCancelDocumentCallback(
        string $callbackQueryId,
        string|int $chatId,
        string|int|null $messageId,
        TelegramUser $telegramUser,
        TelegramDocument $telegramDocument,
    ): void {
        if ($telegramDocument->getStatus() === TelegramDocumentStatus::Written) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Документ уже записан.');

            return;
        }

        $telegramDocument->setStatus(TelegramDocumentStatus::Cancelled);
        $this->entityManager->flush();

        $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Отклонено.');
        $this->removeInlineKeyboard($chatId, $messageId);
        $this->telegramMessageSender->sendText(
            $chatId,
            'Документ отклонен.',
            $telegramUser,
            $telegramDocument,
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseCallbackData(string $data): array
    {
        $parts = explode(':', $data, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string, mixed> $chat
     * @param array<string, mixed> $message
     * @param array<string, mixed> $update
     */
    private function createIncomingMessageLog(array $chat, array $message, array $update): TelegramMessageLog
    {
        $log = new TelegramMessageLog();
        $log->setChatId($chat['id']);
        $log->setMessageId($message['message_id'] ?? null);
        $log->setDirection(TelegramMessageDirection::Incoming);
        $log->setStatus(TelegramMessageStatus::Received);
        $log->setText($message['text'] ?? null);
        $log->setPayload($update);

        return $log;
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $document
     */
    private function createTelegramDocument(
        TelegramUser $telegramUser,
        array $message,
        array $document,
    ): TelegramDocument {
        $fileName = $document['file_name'] ?? null;

        if ($fileName !== null && !is_string($fileName)) {
            $fileName = null;
        }

        $telegramDocument = new TelegramDocument();
        $telegramDocument->setTelegramUser($telegramUser);
        $telegramDocument->setChatId($message['chat']['id']);
        $telegramDocument->setMessageId($message['message_id'] ?? null);
        $telegramDocument->setTelegramFileId($document['file_id']);
        $telegramDocument->setTelegramFileUniqueId($document['file_unique_id']);
        $telegramDocument->setOriginalFilename($fileName);
        $telegramDocument->setMimeType($document['mime_type']);
        $telegramDocument->setSizeBytes($document['file_size']);
        $telegramDocument->setStatus(TelegramDocumentStatus::Received);
        $telegramDocument->setSource(TelegramDocumentSource::DirectMessage);

        return $telegramDocument;
    }

    private function removeInlineKeyboard(string|int $chatId, string|int|null $messageId): void
    {
        if ($messageId === null) {
            return;
        }

        try {
            $this->telegramBotClient->editMessageReplyMarkup($chatId, $messageId);
        } catch (\Throwable) {
            /*
             * Удаление кнопок - UX-улучшение, а не бизнес-критичная операция.
             * Если Telegram не дал отредактировать сообщение, запись в таблицу
             * или отмена документа уже выполнены, поэтому webhook не роняем.
             */
        }
    }
}
