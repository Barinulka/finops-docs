<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ClientCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Client::class;
    }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Клиент')
            ->setEntityLabelInPlural('Клиенты')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields([
                'name',
                'legalName',
                'taxId',
                'registrationNumber',
                'email',
                'phone',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield TextField::new('name', 'Название');
        yield TextField::new('legalName', 'Юридическое название')
            ->hideOnIndex();

        yield TextField::new('taxId', 'ИНН / Tax ID')
            ->setRequired(false);

        yield TextField::new('registrationNumber', 'Рег. номер')
            ->setRequired(false)
            ->hideOnIndex();

        yield EmailField::new('email', 'Email')
            ->setRequired(false);

        yield TextField::new('phone', 'Телефон')
            ->setRequired(false);

        yield TextareaField::new('notes', 'Заметки')
            ->setRequired(false)
            ->hideOnIndex();

        yield BooleanField::new('isActive', 'Активен');

        yield DateTimeField::new('createdAt', 'Создан')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Обновлен')
            ->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof Client) {
            throw new \LogicException(sprintf('Expected %s, got %s.', Client::class, $entityInstance::class));
        }

        $entityManager->persist($entityInstance);

        $this->auditLogger->created(
            'client',
            (string) $entityInstance->getId(),
            $this->serializeClient($entityInstance),
        );

        $entityManager->flush();
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof Client) {
            throw new \LogicException(sprintf('Expected %s, got %s.', Client::class, $entityInstance::class));
        }

        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);

        $this->auditLogger->updated(
            'client',
            (string) $entityInstance->getId(),
            $this->serializeClientOriginalData($originalData),
            $this->serializeClient($entityInstance),
        );

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof Client) {
            throw new \LogicException(sprintf('Expected %s, got %s.', Client::class, $entityInstance::class));
        }

        $this->auditLogger->deleted(
            'client',
            (string) $entityInstance->getId(),
            $this->serializeClient($entityInstance),
        );

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private function serializeClient(Client $client): array
    {
        return [
            'name' => $client->getName(),
            'legalName' => $client->getLegalName(),
            'taxId' => $client->getTaxId(),
            'registrationNumber' => $client->getRegistrationNumber(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'isActive' => $client->isActive(),
        ];
    }

    /**
     * @param array<string, mixed> $originalData
     */
    private function serializeClientOriginalData(array $originalData): array
    {
        return [
            'name' => $originalData['name'] ?? null,
            'legalName' => $originalData['legalName'] ?? null,
            'taxId' => $originalData['taxId'] ?? null,
            'registrationNumber' => $originalData['registrationNumber'] ?? null,
            'email' => $originalData['email'] ?? null,
            'phone' => $originalData['phone'] ?? null,
            'isActive' => $originalData['isActive'] ?? null,
        ];
    }
}
