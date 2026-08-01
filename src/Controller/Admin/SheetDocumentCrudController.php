<?php

namespace App\Controller\Admin;

use App\Entity\SheetDocument;
use App\Enum\SheetDocumentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use App\Message\WriteSheetDocumentToGoogleSheetsMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

final class SheetDocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SheetDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Документ для таблицы')
            ->setEntityLabelInPlural('Документы для таблицы')
            ->setDefaultSort(['uploadedAt' => 'DESC'])
            ->setSearchFields([
                'originalFilename',
                'checksumSha256',
                'errorMessage',
                'rawText',
            ])
            ->overrideTemplate('crud/index', 'admin/sheet_document/index.html.twig')
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('uploadSheetDocuments', 'Загрузить PDF', 'fa fa-upload')
            ->linkToRoute('admin_sheet_document_upload')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-primary');
        $writeToGoogleSheets = Action::new('queueWriteToGoogleSheets', 'Записать в таблицу', 'fa fa-table')
            ->linkToCrudAction('queueWriteToGoogleSheets')
            ->displayIf(static fn (SheetDocument $sheetDocument): bool => $sheetDocument->getStatus()->canBeWritten())
            ->addCssClass('btn btn-primary');
        $queueSelectedToGoogleSheets = Action::new('queueSelectedToGoogleSheets', 'Записать выбранные в таблицу', 'fa fa-table')
            ->linkToCrudAction('queueSelectedToGoogleSheets')
            ->createAsBatchAction();

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Action::INDEX, $writeToGoogleSheets)
            ->add(Action::DETAIL, $writeToGoogleSheets)
            ->add(Crud::PAGE_INDEX, $queueSelectedToGoogleSheets);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield TextField::new('originalFilename', 'Файл');

        yield TextField::new('status.value', 'Статус')
            ->formatValue(function (mixed $value, SheetDocument $document): string {
                $status = $document->getStatus();

                return sprintf(
                    '<span class="badge %s">%s</span>',
                    htmlspecialchars($status->badgeClass(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($status->label(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml();

        yield NumberField::new('parserConfidence', 'Уверенность парсера')
            ->setNumDecimals(2)
            ->hideOnIndex();

        yield TextField::new('uploadedBy.email', 'Загрузил')
            ->hideOnIndex();

        yield IntegerField::new('sizeBytes', 'Размер, байт')
            ->hideOnIndex();

        yield TextField::new('mimeType', 'MIME')
            ->hideOnIndex();

        yield TextField::new('checksumSha256', 'SHA-256')
            ->hideOnIndex();

        yield TextField::new('storagePath', 'Путь в хранилище')
            ->onlyOnDetail();

        yield ArrayField::new('parsedFields', 'Извлеченные поля')
            ->onlyOnDetail()
            ->setTemplatePath('admin/sheet_document/field/parsed_fields.html.twig');

        yield ArrayField::new('parserWarnings', 'Предупреждения парсера')
            ->onlyOnDetail();

        yield ArrayField::new('validationErrors', 'Ошибки проверки')
            ->onlyOnDetail();

        yield TextareaField::new('errorMessage', 'Ошибка')
            ->onlyOnDetail();

        yield TextareaField::new('rawText', 'Текст PDF')
            ->onlyOnDetail();

        yield DateTimeField::new('uploadedAt', 'Загружен')
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('queuedForParsingAt', 'Очередь парсинга')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('parsedAt', 'Распарсен')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('queuedForWriteAt', 'Очередь записи')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('writtenAt', 'Записан')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('failedAt', 'Ошибка')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');
    }

    #[AdminRoute(path: '/{entityId}/queue-write-to-google-sheets', name: 'queue_write')]
    public function queueWriteToGoogleSheets(AdminContext $context): RedirectResponse
    {
        $sheetDocument = $context->getEntity()->getInstance();

        if (!$sheetDocument instanceof SheetDocument) {
            throw new \RuntimeException('Sheet document was not found in admin context.');
        }

        if (!$sheetDocument->getStatus()->canBeWritten()) {
            $this->addFlash('warning', 'Документ в текущем статусе нельзя записать в Google таблицу.');

            return $this->redirectToRoute('admin_sheet_document_index');
        }

        $sheetDocument
            ->setStatus(SheetDocumentStatus::QueuedForWrite)
            ->setQueuedForWriteAt(new \DateTimeImmutable())
            ->setErrorMessage(null);

        $this->entityManager->flush();

        $this->messageBus->dispatch(new WriteSheetDocumentToGoogleSheetsMessage((string) $sheetDocument->getId()));

        $this->addFlash('success', 'Документ поставлен в очередь на запись в Google таблицу.');

        return $this->redirectToRoute('admin_sheet_document_index');
    }

    #[AdminRoute(path: '/queue-selected-to-google-sheets', name: 'queue_selected_to_google_sheets')]
    public function queueSelectedToGoogleSheets(BatchActionDto $batchActionDto): RedirectResponse
    {
        $entityIds = $batchActionDto->getEntityIds();

        if ($entityIds === []) {
            $this->addFlash('info', 'Документы не выбраны.');

            return $this->redirectToRoute('admin_sheet_document_index');
        }

        $queuedCount = 0;
        $skippedCount = 0;
        $now = new \DateTimeImmutable();

        foreach ($entityIds as $entityId) {
            $sheetDocument = $this->entityManager->find(SheetDocument::class, $entityId);

            if (!$sheetDocument instanceof SheetDocument) {
                ++$skippedCount;

                continue;
            }

            if (!$sheetDocument->getStatus()->canBeWritten() || $sheetDocument->getStatus() === SheetDocumentStatus::Written) {
                ++$skippedCount;

                continue;
            }

            $sheetDocument
                ->setStatus(SheetDocumentStatus::QueuedForWrite)
                ->setQueuedForWriteAt($now)
                ->setErrorMessage(null);

            $this->messageBus->dispatch(new WriteSheetDocumentToGoogleSheetsMessage((string) $sheetDocument->getId()));

            ++$queuedCount;
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf(
            'В очередь на запись поставлено: %d. Пропущено: %d.',
            $queuedCount,
            $skippedCount,
        ));

        return $this->redirectToRoute('admin_sheet_document_index');
    }
}
