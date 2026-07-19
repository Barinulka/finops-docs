<?php

namespace App\MessageHandler;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Message\ProcessTelegramDocumentMessage;
use App\Repository\TelegramDocumentRepository;
use App\Service\Telegram\TelegramDocumentProcessingService;
use App\Service\Telegram\TelegramDocumentReviewMessageFactory;
use App\Service\Telegram\TelegramMessageSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessTelegramDocumentMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TelegramDocumentRepository $telegramDocumentRepository,
        private TelegramDocumentProcessingService $telegramDocumentProcessingService,
        private TelegramDocumentReviewMessageFactory $reviewMessageFactory,
        private TelegramMessageSender $telegramMessageSender,
    ) {
    }

    public function __invoke(ProcessTelegramDocumentMessage $message): void
    {
        $telegramDocument = $this->telegramDocumentRepository->find($message->telegramDocumentId);

        if (!$telegramDocument instanceof TelegramDocument) {
            return;
        }

        $this->telegramDocumentProcessingService->process($telegramDocument);

        $this->entityManager->flush();

        $this->sendProcessingResult($telegramDocument);
    }

    private function sendProcessingResult(TelegramDocument $telegramDocument): void
    {
        $telegramUser = $telegramDocument->getTelegramUser();
        $chatId = $telegramDocument->getChatId();

        if ($telegramUser === null || $chatId === null) {
            return;
        }

        if (in_array($telegramDocument->getStatus(), [
            TelegramDocumentStatus::Parsed,
            TelegramDocumentStatus::NeedsReview,
            TelegramDocumentStatus::ValidationFailed,
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
}
