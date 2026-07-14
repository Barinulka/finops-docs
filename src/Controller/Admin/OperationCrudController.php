<?php

namespace App\Controller\Admin;

use App\Entity\Operation;
use App\Entity\User;
use App\Enum\OperationStatus;
use App\Enum\OperationType;
use App\Service\Operation\OperationConfirmer;
use App\Service\Operation\Exception\OperationConfirmationException;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

final class OperationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OperationConfirmer $operationConfirmer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Operation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Операция')
            ->setEntityLabelInPlural('Операции')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields([
                'externalReference',
                'contractNumber',
                'purpose',
                'paymentAmount',
                'paymentCurrency',
                'exchangeRateRaw',
                'executionTermRaw',
                'beneficiaryName',
                'beneficiaryBankName',
                'beneficiarySwift',
                'beneficiaryAccount',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $confirmOperation = Action::new('confirmOperation', 'Подтвердить', 'fa fa-check')
            ->linkToCrudAction('confirmOperation')
            ->renderAsForm()
            ->displayIf(static fn (Operation $operation): bool => $operation->getStatus() !== OperationStatus::Confirmed)
            ->addCssClass('btn btn-success');

        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $confirmOperation)
            ->setPermission('confirmOperation', 'ROLE_MANAGER');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Основное');

        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield AssociationField::new('client', 'Клиент')
            ->hideOnForm();

        yield AssociationField::new('sourceDocument', 'Документ')
            ->onlyOnDetail()
            ->formatValue(function (mixed $value, Operation $operation): string {
                $document = $operation->getSourceDocument();

                if ($document === null) {
                    return 'Нет';
                }

                $url = $this->adminUrlGenerator
                    ->setController(DocumentCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId((string) $document->getId())
                    ->generateUrl();

                return sprintf(
                    '<a href="%s">%s</a>',
                    htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($document->getOriginalFilename() ?? (string) $document->getId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml();

        yield TextField::new('status.value', 'Статус')
            ->formatValue(function (mixed $value, Operation $operation): string {
                $status = $operation->getStatus();

                return sprintf(
                    '<span class="badge %s">%s</span>',
                    htmlspecialchars($status->badgeClass(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($status->label(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
            })
            ->renderAsHtml()
            ->hideOnForm();

        yield TextField::new('type.value', 'Тип')
            ->formatValue(function (mixed $value, Operation $operation): string {
                return $operation->getType()->label();
            })
            ->hideOnForm();

        yield ChoiceField::new('type', 'Тип')
            ->setChoices([
                'Платеж' => OperationType::Payment,
                'Перевод' => OperationType::Transfer,
                'Конвертация валют' => OperationType::CurrencyConversion,
                'Другое' => OperationType::Other,
            ])
            ->onlyOnForms();

        yield FormField::addFieldset('Финансы');

        yield TextField::new('paymentSummary', 'Платеж')
            ->setVirtual(true)
            ->onlyOnIndex()
            ->formatValue(function (mixed $value, Operation $operation): string {
                $amount = $operation->getPaymentAmount();
                $currency = $operation->getPaymentCurrency();

                if ($amount === null && $currency === null) {
                    return 'Нет';
                }

                return trim(sprintf('%s %s', $amount ?? '', $currency ?? ''));
            });

        yield TextField::new('paymentAmount', 'Сумма платежа')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('paymentCurrency', 'Валюта платежа')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('exchangeRate', 'Курс')
            ->setRequired(false);

        yield TextField::new('exchangeRateRaw', 'Курс, исходное значение')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('agencyFeeAmountRub', 'Агентское вознаграждение, руб.')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('totalAmountRub', 'Итого, руб.')
            ->setRequired(false);

        yield FormField::addFieldset('Исполнение');

        yield TextField::new('executionTermRaw', 'Срок выполнения')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('externalReference', 'Внешний номер')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('contractNumber', 'Номер договора')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextareaField::new('purpose', 'Назначение')
            ->setRequired(false)
            ->hideOnIndex();

        yield FormField::addFieldset('Получатель');

        yield TextField::new('beneficiaryName', 'Получатель')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('beneficiaryBankName', 'Банк получателя')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('beneficiarySwift', 'SWIFT')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('beneficiaryAccount', 'Счет получателя')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextareaField::new('beneficiaryRawDetails', 'Реквизиты получателя')
            ->setRequired(false)
            ->onlyOnDetail();

        yield TextField::new('createdBy.email', 'Создал')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('confirmedAt', 'Подтверждена')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield TextField::new('confirmedBy.email', 'Подтвердил')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Создана')
            ->hideOnForm()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('updatedAt', 'Обновлена')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormat('dd.MM.yyyy HH:mm:ss');
    }

    #[AdminRoute(path: '/{entityId}/confirm', name: 'confirm', options: ['methods' => ['POST']])]
    public function confirmOperation(AdminContext $context): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $operation = $context->getEntity()->getInstance();

        if (!$operation instanceof Operation) {
            throw new \LogicException(sprintf('Expected %s, got %s.', Operation::class, get_debug_type($operation)));
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->operationConfirmer->confirm($operation, $user);
            $this->addFlash('success', 'Операция подтверждена.');
        } catch (OperationConfirmationException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId((string) $operation->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof Operation) {
            throw new \LogicException(sprintf('Expected %s, got %s.', Operation::class, $entityInstance::class));
        }

        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);

        $this->auditLogger->updated(
            'operation',
            (string) $entityInstance->getId(),
            $this->serializeOperationOriginalData($originalData),
            $this->serializeOperation($entityInstance),
        );

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('client', 'Клиент'))
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Черновик' => OperationStatus::Draft,
                'Подтверждена' => OperationStatus::Confirmed,
                'Отменена' => OperationStatus::Cancelled,
            ]))
            ->add(ChoiceFilter::new('type', 'Тип')->setChoices([
                'Платеж' => OperationType::Payment,
                'Перевод' => OperationType::Transfer,
                'Конвертация валют' => OperationType::CurrencyConversion,
                'Другое' => OperationType::Other,
            ]))
            ->add(TextFilter::new('paymentCurrency', 'Валюта'))
            ->add(DateTimeFilter::new('createdAt', 'Дата создания'));
    }

    private function serializeOperation(Operation $operation): array
    {
        return [
            'status' => $operation->getStatus()->value,
            'type' => $operation->getType()->value,
            'paymentAmount' => $operation->getPaymentAmount(),
            'paymentCurrency' => $operation->getPaymentCurrency(),
            'exchangeRate' => $operation->getExchangeRate(),
            'exchangeRateRaw' => $operation->getExchangeRateRaw(),
            'agencyFeeAmountRub' => $operation->getAgencyFeeAmountRub(),
            'totalAmountRub' => $operation->getTotalAmountRub(),
            'executionTermRaw' => $operation->getExecutionTermRaw(),
            'executionDueDate' => $operation->getExecutionDueDate()?->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $originalData
     */
    private function serializeOperationOriginalData(array $originalData): array
    {
        return [
            'status' => $originalData['status']?->value ?? null,
            'type' => $originalData['type']?->value ?? null,
            'paymentAmount' => $originalData['paymentAmount'] ?? null,
            'paymentCurrency' => $originalData['paymentCurrency'] ?? null,
            'exchangeRate' => $originalData['exchangeRate'] ?? null,
            'exchangeRateRaw' => $originalData['exchangeRateRaw'] ?? null,
            'agencyFeeAmountRub' => $originalData['agencyFeeAmountRub'] ?? null,
            'totalAmountRub' => $originalData['totalAmountRub'] ?? null,
            'executionTermRaw' => $originalData['executionTermRaw'] ?? null,
            'executionDueDate' => $originalData['executionDueDate']?->format('Y-m-d') ?? null,
        ];
    }
}
