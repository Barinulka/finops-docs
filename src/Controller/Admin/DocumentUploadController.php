<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\DocumentUploadType;
use App\Form\Model\DocumentUploadData;
use App\Service\Document\DocumentUploadService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DocumentUploadController extends AbstractController
{
    #[AdminRoute(path: '/documents/upload', name: 'document_upload')]
    public function __invoke(
        Request $request,
        Security $security,
        DocumentUploadService $documentUploadService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_OPERATOR');

        $user = $security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $data = new DocumentUploadData();
        $form = $this->createForm(DocumentUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $document = $documentUploadService->upload($data, $user);

            $this->addFlash('success', 'Документ загружен.');

            return $this->redirectToRoute('admin_document_detail', [
                'entityId' => (string) $document->getId(),
            ]);
        }

        return $this->render('admin/document/upload.html.twig', [
            'form' => $form,
        ]);
    }
}
