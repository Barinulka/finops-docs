<?php

namespace App\Controller\Admin;

use App\Entity\TelegramUser;
use App\Enum\Telegram\TelegramUserRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class TelegramUserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TelegramUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Telegram пользователь')
            ->setEntityLabelInPlural('Telegram пользователи')
            ->setPageTitle(Crud::PAGE_INDEX, 'Telegram пользователи')
            ->setPageTitle(Crud::PAGE_NEW, 'Новый Telegram пользователь')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать Telegram пользователя')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['telegramId', 'username', 'firstName', 'lastName']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield TextField::new('telegramId', 'Telegram ID');

        yield TextField::new('username', 'Username')
            ->setRequired(false);

        yield TextField::new('firstName', 'Имя')
            ->setRequired(false);

        yield TextField::new('lastName', 'Фамилия')
            ->setRequired(false);

        yield TextField::new('languageCode', 'Язык')
            ->setRequired(false)
            ->hideOnIndex();

        yield ChoiceField::new('role', 'Роль')
            ->setChoices([
                'Оператор' => TelegramUserRole::Operator,
                'Менеджер' => TelegramUserRole::Manager,
                'Администратор' => TelegramUserRole::Admin,
            ])
            ->formatValue(static fn (?TelegramUserRole $value): string => $value?->label() ?? '')
            ->renderAsBadges();

        yield BooleanField::new('isActive', 'Активен');

        yield AssociationField::new('linkedUser', 'Пользователь CRM')
            ->setRequired(false);

        yield DateTimeField::new('lastSeenAt', 'Последняя активность')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Создан')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Обновлен')
            ->hideOnForm();
    }
}
