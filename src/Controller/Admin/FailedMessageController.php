<?php

namespace App\Controller\Admin;

use App\Service\Messenger\FailedMessageService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FailedMessageController extends AbstractController
{
    #[AdminRoute(path: '/messenger/failed', name: 'messenger_failed')]
    public function index(FailedMessageService $failedMessageService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/messenger/failed.html.twig', [
            'messages' => $failedMessageService->list(),
        ]);
    }

    #[AdminRoute(path: '/messenger/failed/{id}/retry', name: 'messenger_failed_retry', options: ['methods' => ['POST']])]
    public function retry(string $id, Request $request, FailedMessageService $failedMessageService): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(sprintf('failed_message_retry_%s', $id), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $failedMessageService->retry($id);
        $this->addFlash('success', 'Сообщение поставлено обратно в очередь.');

        return $this->redirectToRoute('admin_messenger_failed');
    }

    #[AdminRoute(path: '/messenger/failed/{id}/remove', name: 'messenger_failed_remove', options: ['methods' => ['POST']])]
    public function remove(string $id, Request $request, FailedMessageService $failedMessageService): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(sprintf('failed_message_remove_%s', $id), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $failedMessageService->remove($id);
        $this->addFlash('success', 'Сообщение удалено из failed-очереди.');

        return $this->redirectToRoute('admin_messenger_failed');
    }
}
