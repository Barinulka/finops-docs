<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramUser;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Repository\TelegramDocumentRepository;
use App\Repository\TelegramUserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TelegramCallbackHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TelegramUserRepository $telegramUserRepository,
        private TelegramDocumentRepository $telegramDocumentRepository,
        private TelegramBotClient $telegramBotClient,
        private TelegramMessageSender $telegramMessageSender,
        private TelegramDocumentGoogleSheetWriter $googleSheetWriter,
    ) {
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    public function handle(array $callbackQuery): void
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

        if (!$this->userOwnsDocument($telegramUser, $telegramDocument)) {
            $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Нет доступа к документу.');

            return;
        }

        match ($action) {
            'write_to_sheet' => $this->handleWriteToSheetCallback($callbackQueryId, $chatId, $messageId, $telegramUser, $telegramDocument),
            'cancel_document' => $this->handleCancelDocumentCallback($callbackQueryId, $chatId, $messageId, $telegramUser, $telegramDocument),
            default => $this->telegramBotClient->answerCallbackQuery($callbackQueryId, 'Неизвестное действие.'),
        };
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
            TelegramDocumentStatus::ValidationFailed,
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

    private function userOwnsDocument(TelegramUser $telegramUser, TelegramDocument $telegramDocument): bool
    {
        return $telegramDocument->getTelegramUser()?->getId()?->__toString()
            === $telegramUser->getId()?->__toString();
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
             * Удаление кнопок - UX-улучшение.
             * Если Telegram не дал отредактировать сообщение, основное действие уже выполнено,
             * поэтому webhook не должен падать.
             */
        }
    }
}
