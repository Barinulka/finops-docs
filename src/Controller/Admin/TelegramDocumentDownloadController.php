<?php

namespace App\Controller\Admin;

use App\Entity\TelegramDocument;
use App\Repository\TelegramDocumentRepository;
use App\Service\Telegram\TelegramFileDownloader;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class TelegramDocumentDownloadController extends AbstractController
{
    #[AdminRoute(path: '/telegram-document/{entityId}/download', name: 'telegram_document_download')]
    public function __invoke(
        string $entityId,
        TelegramDocumentRepository $telegramDocumentRepository,
        TelegramFileDownloader $telegramFileDownloader,
    ): Response {
        $telegramDocument = $telegramDocumentRepository->find($entityId);

        if (!$telegramDocument instanceof TelegramDocument) {
            throw $this->createNotFoundException('Telegram document not found.');
        }

        $this->denyAccessUnlessGranted('ROLE_OPERATOR');

        $fileId = $telegramDocument->getTelegramFileId();

        if ($fileId === null) {
            throw $this->createNotFoundException('Telegram file id is missing.');
        }

        $downloadedFile = $telegramFileDownloader->download($fileId);

        $filename = $telegramDocument->getOriginalFilename() ?: sprintf('telegram-document-%s.pdf', $telegramDocument->getId());

        $response = new Response($downloadedFile->contents);
        $response->headers->set('Content-Type', $telegramDocument->getMimeType() ?? 'application/pdf');

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename,
            sprintf('telegram-document-%s.pdf', $telegramDocument->getId()),
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
