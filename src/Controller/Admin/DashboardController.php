<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use App\Repository\ClientRepository;
use App\Repository\DocumentRepository;
use App\Repository\OperationRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly OperationRepository $operationRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'clientsCount' => $this->clientRepository->count([]),
            'documentsCount' => $this->documentRepository->count([]),
            'operationsCount' => $this->operationRepository->count([]),
            'auditLogsCount' => $this->auditLogRepository->count([]),
        ]);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/app.scss');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('CRM');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Панель управления', 'fa fa-home');

        yield MenuItem::section('CRM');
        yield MenuItem::linkTo(ClientCrudController::class, 'Клиенты', 'fa fa-building');
        yield MenuItem::linkTo(DocumentCrudController::class, 'Документы', 'fa fa-file-lines');
        yield MenuItem::linkTo(OperationCrudController::class, 'Операции', 'fa fa-money-bill-transfer');

        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::section('Telegram');
            yield MenuItem::linkTo(TelegramUserCrudController::class, 'Пользователи', 'fa fa-paper-plane');
            yield MenuItem::linkTo(TelegramDocumentCrudController::class, 'Документы', 'fa fa-file-pdf');
            yield MenuItem::linkTo(TelegramMessageLogCrudController::class, 'Журнал сообщений', 'fa fa-comments');

            yield MenuItem::section('Интеграции');
            yield MenuItem::linkTo(GoogleSheetAppendLogCrudController::class, 'Google Sheets log', 'fa fa-table');

            yield MenuItem::section('Система');
            yield MenuItem::linkTo(AuditLogCrudController::class, 'Аудит', 'fa fa-clock-rotate-left');
            yield MenuItem::linkToRoute('Ошибки очереди', 'fa fa-triangle-exclamation', 'admin_messenger_failed');
        }
    }
}
