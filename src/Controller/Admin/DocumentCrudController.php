<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\Operation;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Message\ParseDocumentMessage;
use App\Repository\OperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class DocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly OperationRepository $operationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Документ')
            ->setEntityLabelInPlural('Документы')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields([
                'originalFilename',
                'storagePath',
                'mimeType',
                'checksumSha256',
                'parserVersion',
                'parseError',
            ])
            ->overrideTemplate('crud/detail', 'admin/document/detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $uploadDocument = Action::new('uploadDocument', 'Загрузить PDF', 'fa fa-upload')
            ->linkToRoute('admin_document_upload')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-primary');

        $editRelatedOperation = Action::new('editRelatedOperation', 'Редактировать операцию', 'fa fa-pen')
            ->linkToUrl(function (Document $document): string {
                $operation = $this->findOperation($document);

                if (!$operation instanceof Operation) {
                    return '#';
                }

                return $this->adminUrlGenerator
                    ->setController(OperationCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId((string) $operation->getId())
                    ->generateUrl();
            })
            ->displayIf(fn (Document $document): bool => $this->findOperation($document) instanceof Operation)
            ->addCssClass('btn btn-primary');

        $reparseDocument = Action::new('reparseDocument', 'Запустить парсинг повторно', 'fa fa-rotate')
            ->linkToCrudAction('reparseDocument')
            ->displayIf(function (Document $document): bool {
                return $document->getStatus() !== DocumentStatus::Parsing;
            })
            ->renderAsForm()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $uploadDocument)
            ->add(Crud::PAGE_DETAIL, $editRelatedOperation)
            ->add(Crud::PAGE_DETAIL, $reparseDocument);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Основное')
            ->onlyOnDetail();

        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield AssociationField::new('client', 'Клиент');

        yield TextField::new('originalFilename', 'Файл')
            ->formatValue(function (?string $value, Document $document): string {
                $url = $this->adminUrlGenerator
                    ->setController(DocumentDownloadController::class)
                    ->setRoute('admin_document_download', [
                        'entityId' => (string) $document->getId(),
                    ])
                    ->generateUrl();

                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($value ?? 'Документ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml();

        yield TextField::new('status.value', 'Статус')
            ->formatValue(function (mixed $value, Document $document): string {
                $status = $document->getStatus();

                return sprintf(
                    '<span class="badge %s">%s</span>',
                    htmlspecialchars($status->badgeClass(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($status->label(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml();

        yield TextField::new('type.value', 'Тип')
            ->formatValue(function (mixed $value, Document $document): string {
                return $document->getType()->label();
            });

        yield AssociationField::new('uploadedBy', 'Загрузил')
            ->hideOnIndex();

        yield FormField::addFieldset('Файл')
            ->onlyOnDetail();

        yield IntegerField::new('sizeBytes', 'Размер, байт')
            ->hideOnIndex();

        yield TextField::new('mimeType', 'MIME')
            ->hideOnIndex();

        yield TextField::new('checksumSha256', 'SHA-256')
            ->hideOnIndex();

        yield TextField::new('storagePath', 'Путь в хранилище')
            ->onlyOnDetail();

        yield FormField::addFieldset('Парсинг')
            ->onlyOnDetail();

        yield TextField::new('parserVersion', 'Версия парсера')
            ->hideOnIndex();

        yield TextareaField::new('parseError', 'Ошибка парсинга')
            ->onlyOnDetail();

        yield DateTimeField::new('queuedAt', 'Поставлен в очередь')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('parsedAt', 'Распарсен')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield TextField::new('parsedConfidenceText', 'Уверенность парсера')
            ->onlyOnDetail();

        yield TextareaField::new('parsedWarningsText', 'Предупреждения парсера')
            ->onlyOnDetail();

        yield TextareaField::new('parsedFieldsText', 'Извлеченные поля')
            ->onlyOnDetail();

        yield TextField::new('relatedOperationId', 'Операция')
            ->onlyOnDetail()
            ->formatValue(function (mixed $value, Document $document): string {
                $operation = $document->getRelatedOperation();

                if (!$operation instanceof Operation) {
                    return 'Не создана';
                }

                $url = $this->adminUrlGenerator
                    ->setController(OperationCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId((string) $operation->getId())
                    ->generateUrl();

                return sprintf(
                    '<a href="%s">%s</a>',
                    htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars((string) $operation->getId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml();

        yield FormField::addFieldset('Текст PDF')
            ->onlyOnDetail();

        yield TextareaField::new('parsedRawText', 'Текст из PDF')
            ->onlyOnDetail();

        yield FormField::addFieldset('Служебное')
            ->onlyOnDetail();

        yield DateTimeField::new('confirmedAt', 'Подтвержден')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('createdAt', 'Создан')
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('updatedAt', 'Обновлен')
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('client', 'Клиент'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Загружен' => DocumentStatus::Uploaded,
                'В очереди' => DocumentStatus::Queued,
                'Обрабатывается' => DocumentStatus::Parsing,
                'Распарсен' => DocumentStatus::Parsed,
                'Требует проверки' => DocumentStatus::NeedsReview,
                'Подтвержден' => DocumentStatus::Confirmed,
                'Ошибка' => DocumentStatus::Failed,
            ]))
            ->add(ChoiceFilter::new('type', 'Тип')->setChoices([
                'Неизвестный' => DocumentType::Unknown,
                'Платежное поручение' => DocumentType::PaymentInstruction,
                'Заявка к агентскому договору' => DocumentType::ApplicationForm,
                'Заявка ASSTRA' => DocumentType::AsstraApplication,
                'Субагентское поручение' => DocumentType::SubagentInstruction,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Дата создания'));
    }

    #[AdminRoute(path: '/{entityId}/reparse', name: 'reparse', options: ['methods' => ['POST']])]
    public function reparseDocument(): Response
    {
        $context = $this->getContext();
        $document = $context?->getEntity()?->getInstance();

        if (!$document instanceof Document) {
            throw $this->createNotFoundException('Document not found.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $document
            ->setStatus(DocumentStatus::Queued)
            ->setQueuedAt(new \DateTimeImmutable())
            ->setParseError(null);

        $this->entityManager->flush();

        $this->messageBus->dispatch(new ParseDocumentMessage(
            (string) $document->getId(),
            (string) $user->getId(),
        ));

        $this->addFlash('success', 'Документ поставлен в очередь на повторный парсинг.');

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId((string) $document->getId())
            ->generateUrl();

        return new RedirectResponse($url);
    }

    private function findOperation(Document $document): ?Operation
    {
        return $this->operationRepository->findOneBy([
            'sourceDocument' => $document,
        ]);
    }
}
