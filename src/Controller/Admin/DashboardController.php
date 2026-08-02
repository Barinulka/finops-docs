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
use App\Enum\SheetDocumentStatus;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use App\Repository\GoogleSheetAppendLogRepository;
use App\Repository\SheetDocumentRepository;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly OperationRepository $operationRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly SheetDocumentRepository $sheetDocumentRepository,
        private readonly GoogleSheetAppendLogRepository $googleSheetAppendLogRepository,
    ) {
    }

    public function index(): Response
    {
        $statusesForErrors = [
            SheetDocumentStatus::Failed,
            SheetDocumentStatus::WriteFailed,
        ];

        return $this->render('admin/dashboard.html.twig', [
            'clientsCount' => $this->clientRepository->count([]),
            'documentsCount' => $this->documentRepository->count([]),
            'operationsCount' => $this->operationRepository->count([]),
            'auditLogsCount' => $this->auditLogRepository->count([]),

            'sheetDocumentsTotalCount' => $this->sheetDocumentRepository->count([]),
            'sheetDocumentsQueuedCount' => $this->sheetDocumentRepository->count([
                'status' => [
                    SheetDocumentStatus::QueuedForParsing,
                    SheetDocumentStatus::Parsing,
                    SheetDocumentStatus::QueuedForWrite,
                    SheetDocumentStatus::Writing,
                ],
            ]),
            'sheetDocumentsWrittenCount' => $this->sheetDocumentRepository->count([
                'status' => SheetDocumentStatus::Written,
            ]),
            'sheetDocumentsErrorCount' => $this->sheetDocumentRepository->count([
                'status' => $statusesForErrors,
            ]),
            'latestSheetDocuments' => $this->sheetDocumentRepository->findBy(
                [],
                ['createdAt' => 'DESC'],
                8,
            ),
            'latestGoogleSheetLogs' => $this->googleSheetAppendLogRepository->findBy(
                [],
                ['createdAt' => 'DESC'],
                8,
            ),
            'latestGoogleSheetFailedCount' => $this->googleSheetAppendLogRepository->count([
                'status' => GoogleSheetAppendStatus::Failed,
            ]),
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

//        yield MenuItem::section('CRM');
//        yield MenuItem::linkTo(ClientCrudController::class, 'Клиенты', 'fa fa-building');
//        yield MenuItem::linkTo(DocumentCrudController::class, 'Документы', 'fa fa-file-lines');
//        yield MenuItem::linkTo(OperationCrudController::class, 'Операции', 'fa fa-money-bill-transfer');

        if ($this->isGranted('ROLE_ADMIN')) {
//            yield MenuItem::section('Telegram');
//            yield MenuItem::linkTo(TelegramUserCrudController::class, 'Пользователи', 'fa fa-paper-plane');
//            yield MenuItem::linkTo(TelegramDocumentCrudController::class, 'Документы', 'fa fa-file-pdf');
//            yield MenuItem::linkTo(TelegramMessageLogCrudController::class, 'Журнал сообщений', 'fa fa-comments');

            yield MenuItem::section('Google Sheets');
            yield MenuItem::linkTo(SheetDocumentCrudController::class, 'Загрузка документов', 'fa fa-file-import');

            yield MenuItem::section('Интеграции');
            yield MenuItem::linkTo(GoogleSheetAppendLogCrudController::class, 'Журнал Google Sheets', 'fa fa-table');

            yield MenuItem::section('Система');
            yield MenuItem::linkTo(UserCrudController::class, 'Пользователи админки', 'fa fa-users');
            yield MenuItem::linkTo(AuditLogCrudController::class, 'Аудит', 'fa fa-clock-rotate-left');
            yield MenuItem::linkToRoute('Ошибки очереди', 'fa fa-triangle-exclamation', 'admin_messenger_failed');
        }
    }
}
