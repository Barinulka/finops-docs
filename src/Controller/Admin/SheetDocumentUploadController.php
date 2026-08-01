<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Model\SheetDocumentUploadData;
use App\Form\SheetDocumentUploadType;
use App\Service\SheetDocument\SheetDocumentUploadService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SheetDocumentUploadController extends AbstractController
{
    #[AdminRoute(path: '/sheet-documents/upload', name: 'sheet_document_upload')]
    public function __invoke(
        Request $request,
        Security $security,
        SheetDocumentUploadService $uploadService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_OPERATOR');

        $user = $security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $data = new SheetDocumentUploadData();
        $form = $this->createForm(SheetDocumentUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $uploadService->upload($data->files, $user);

            if ($result->getUploadedCount() > 0) {
                $this->addFlash('success', sprintf(
                    'Файлов загружено и поставлено в очередь на парсинг: %d.',
                    $result->getUploadedCount(),
                ));
            }

            foreach ($result->getSkippedDuplicates() as $duplicate) {
                $existingDocument = $duplicate['existingDocument'];

                $this->addFlash('warning', sprintf(
                    'Файл "%s" уже загружался ранее. ID документа: %s, статус: %s.',
                    $duplicate['filename'],
                    (string) $existingDocument->getId(),
                    $existingDocument->getStatus()->label(),
                ));
            }

            if ($result->getUploadedCount() === 0 && $result->getSkippedDuplicateCount() === 0) {
                $this->addFlash('info', 'Файлы для загрузки не найдены.');
            }

            return $this->redirectToRoute('admin_sheet_document_index');
        }

        return $this->render('admin/sheet_document/upload.html.twig', [
            'form' => $form,
        ]);
    }
}
