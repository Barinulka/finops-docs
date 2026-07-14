<?php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class AuditLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Событие аудита')
            ->setEntityLabelInPlural('Аудит')
            ->setEntityPermission('ROLE_ADMIN')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields([
                'entityType',
                'entityId',
                'message',
                'ipAddress',
                'userAgent',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Дата')
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield TextField::new('action.value', 'Действие');

        yield TextField::new('entityType', 'Сущность');

        yield TextField::new('entityId', 'ID сущности')
            ->hideOnIndex();

        yield AssociationField::new('actor', 'Пользователь')
            ->setRequired(false);

        yield TextField::new('message', 'Сообщение')
            ->setRequired(false);

        yield TextField::new('ipAddress', 'IP')
            ->setRequired(false);

        yield ArrayField::new('oldValues', 'Старые значения')
            ->onlyOnDetail();

        yield ArrayField::new('newValues', 'Новые значения')
            ->onlyOnDetail();

        yield ArrayField::new('context', 'Контекст')
            ->onlyOnDetail();

        yield TextField::new('userAgent', 'User-Agent')
            ->onlyOnDetail();
    }
}
