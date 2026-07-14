<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController extends AbstractController
{
    #[AdminRoute(path: '/documents/{entityId}/download', name: 'document_download')]
    public function __invoke(
        string $entityId,
        DocumentRepository $documentRepository,
        #[Autowire(service: 'documents.storage')]
        FilesystemOperator $documentsStorage,
    ): Response {
        $document = $documentRepository->find($entityId);

        if (!$document instanceof Document) {
            throw $this->createNotFoundException('Document not found.');
        }

        $this->denyAccessUnlessGranted('ROLE_OPERATOR');

        $stream = $documentsStorage->readStream($document->getStoragePath());

        if ($stream === false) {
            throw $this->createNotFoundException('Document file not found.');
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        });

        $filename = $document->getOriginalFilename() ?? 'document.pdf';

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename,
            sprintf('document-%s.pdf', $document->getId()),
        );

        $response->headers->set('Content-Type', $document->getMimeType() ?? 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
