<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramMessageLog;
use App\Entity\TelegramUser;
use App\Enum\Telegram\TelegramMessageDirection;
use App\Enum\Telegram\TelegramMessageStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TelegramMessageSender
{
    public function __construct(
        private TelegramBotClient $telegramBotClient,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $replyMarkup
     */
    public function sendText(
        string|int $chatId,
        string $text,
        ?TelegramUser $telegramUser = null,
        ?TelegramDocument $telegramDocument = null,
        ?array $replyMarkup = null,
        ?string $parseMode = null,
    ): void {
        $log = new TelegramMessageLog();
        $log->setChatId($chatId);
        $log->setText($text);
        $log->setDirection(TelegramMessageDirection::Outgoing);
        $log->setTelegramUser($telegramUser);
        $log->setTelegramDocument($telegramDocument);
        $log->setPayload([
            'chat_id' => (string) $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => $parseMode,
        ]);

        try {
            $response = $this->telegramBotClient->sendMessage($chatId, $text, $replyMarkup, $parseMode);

            $log->setStatus(TelegramMessageStatus::Sent);
            $log->setResponsePayload($response);
        } catch (\Throwable $exception) {
            $log->setStatus(TelegramMessageStatus::Failed);
            $log->setErrorMessage($exception->getMessage());
        } finally {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }
    }
}
