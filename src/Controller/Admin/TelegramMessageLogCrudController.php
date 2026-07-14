<?php

namespace App\Controller\Admin;

use App\Entity\TelegramMessageLog;
use App\Enum\Telegram\TelegramMessageDirection;
use App\Enum\Telegram\TelegramMessageStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class TelegramMessageLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TelegramMessageLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Сообщение Telegram')
            ->setEntityLabelInPlural('Сообщения Telegram')
            ->setPageTitle(Crud::PAGE_INDEX, 'Telegram message log')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Сообщение Telegram')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['chatId', 'messageId', 'text', 'errorMessage']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable('new', 'edit', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield AssociationField::new('telegramUser', 'Telegram пользователь');

        yield AssociationField::new('telegramDocument', 'Telegram документ')
            ->hideOnIndex();

        yield ChoiceField::new('direction', 'Направление')
            ->setChoices([
                'Входящее' => TelegramMessageDirection::Incoming,
                'Исходящее' => TelegramMessageDirection::Outgoing,
            ])
            ->formatValue(static fn (?TelegramMessageDirection $value): string => $value?->label() ?? '');

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Получено' => TelegramMessageStatus::Received,
                'Отправлено' => TelegramMessageStatus::Sent,
                'Ошибка' => TelegramMessageStatus::Failed,
                'Проигнорировано' => TelegramMessageStatus::Ignored,
            ])
            ->formatValue(static fn (?TelegramMessageStatus $value): string => $value?->label() ?? '')
            ->renderAsBadges();

        yield TextField::new('chatId', 'Chat ID');

        yield TextField::new('messageId', 'Message ID')
            ->hideOnIndex();

        yield TextareaField::new('text', 'Текст')
            ->hideOnIndex();

        yield ArrayField::new('payload', 'Payload')
            ->hideOnIndex();

        yield ArrayField::new('responsePayload', 'Ответ Telegram')
            ->hideOnIndex();

        yield TextareaField::new('errorMessage', 'Ошибка')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Создано')
            ->hideOnForm();
    }
}
