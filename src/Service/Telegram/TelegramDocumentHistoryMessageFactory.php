<?php

namespace App\Service\Telegram;

use App\Entity\TelegramDocument;
use App\Entity\TelegramUser;
use App\Enum\Telegram\TelegramUserRole;
use App\Repository\TelegramDocumentRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TelegramDocumentHistoryMessageFactory
{
    public function __construct(
        private TelegramDocumentRepository $telegramDocumentRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function createText(TelegramUser $telegramUser): string
    {
        $isAdmin = $telegramUser->getRole() === TelegramUserRole::Admin;

        $documents = $isAdmin
            ? $this->telegramDocumentRepository->findRecent(20)
            : $this->telegramDocumentRepository->findRecentForUser($telegramUser, 10);

        if ($documents === []) {
            return $isAdmin
                ? 'Загруженных документов пока нет.'
                : 'У вас пока нет загруженных документов.';
        }

        $lines = [
            $isAdmin ? 'Последние загруженные документы:' : 'Ваши последние загруженные документы:',
            '',
        ];

        foreach ($documents as $index => $document) {
            $lines[] = $this->createDocumentLine($index + 1, $document);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function createDocumentLine(int $number, TelegramDocument $document): string
    {
        $createdAt = $document->getCreatedAt()?->format('d.m.Y H:i') ?? 'дата неизвестна';
        $filename = $document->getOriginalFilename() ?: 'файл без названия';
        $status = $document->getStatus()->label();
        $user = (string) $document->getTelegramUser();

        $writtenText = $document->getWrittenAt() !== null
            ? sprintf('записан в таблицу %s', $document->getWrittenAt()->format('d.m.Y H:i'))
            : 'не записан в таблицу';

        $documentUrl = $this->urlGenerator->generate(
            'admin_telegram_document_download',
            ['entityId' => (string) $document->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return sprintf(
            "%d. %s\nПользователь: %s\nДата: %s\nСтатус: %s, %s\nPDF: %s",
            $number,
            $filename,
            $user,
            $createdAt,
            $status,
            $writtenText,
            $documentUrl,
        );
    }
}
