<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramMessageLog;
use App\Entity\TelegramUser;
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
        private TelegramBotConfig $telegramBotConfig,
        private TelegramFileDownloader $telegramFileDownloader,
        private TelegramDocumentFactory $telegramDocumentFactory,
        private TelegramDocumentParser $telegramDocumentParser,
        private TelegramDocumentReviewMessageFactory $reviewMessageFactory,
        private TelegramDocumentHistoryMessageFactory $historyMessageFactory,
        private TelegramDocumentBusinessValidator $businessValidator,
        private TelegramCallbackHandler $telegramCallbackHandler,
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
            $this->telegramCallbackHandler->handle($callbackQuery);

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

        $text = $message['text'] ?? null;

        if ($document === null) {
            if ($text === '/history') {
                $this->telegramMessageSender->sendText(
                    $chatId,
                    $this->historyMessageFactory->createText($telegramUser),
                    $telegramUser,
                );

                return;
            }

            $this->telegramMessageSender->sendText(
                $chatId,
                "Отправьте PDF-файл.\n\nИстория загрузок: /history",
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
        $telegramDocument = $this->telegramDocumentFactory->createFromTelegramMessage($telegramUser, $message, $document);

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

            $validationResult = $this->businessValidator->validate($telegramDocument);

            if (!$validationResult->valid) {
                $telegramDocument->setStatus(TelegramDocumentStatus::ValidationFailed);
                $telegramDocument->setValidationErrors($validationResult->errors);
            }
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

        if ($telegramDocument->getStatus() === TelegramDocumentStatus::ValidationFailed) {
            $this->telegramMessageSender->sendText(
                $chatId,
                $this->buildValidationFailedMessage($telegramDocument),
                $telegramUser,
                $telegramDocument,
            );

            return;
        }

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

    private function buildValidationFailedMessage(TelegramDocument $telegramDocument): string
    {
        $errors = $telegramDocument->getValidationErrors();

        if ($errors === []) {
            $errors = ['Документ не прошел бизнес-проверку.'];
        }

        return sprintf(
            "Документ не прошел проверку.\n\nНайдены ошибки:\n- %s\n\nИсправьте документ и загрузите исправленную версию.",
            implode("\n- ", $errors),
        );
    }
}
