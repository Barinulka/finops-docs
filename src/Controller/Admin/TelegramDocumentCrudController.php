<?php

namespace App\Controller\Admin;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentSource;
use App\Enum\Telegram\TelegramDocumentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class TelegramDocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TelegramDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Telegram документ')
            ->setEntityLabelInPlural('Telegram документы')
            ->setPageTitle(Crud::PAGE_INDEX, 'Telegram документы')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Telegram документ')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редактировать Telegram документ')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields([
                'originalFilename',
                'chatId',
                'messageId',
                'checksumSha256',
                'errorMessage',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield AssociationField::new('telegramUser', 'Telegram пользователь');

        yield AssociationField::new('document', 'CRM документ')
            ->setRequired(false);

        yield AssociationField::new('duplicateOf', 'Дубликат документа')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('originalFilename', 'Файл')
            ->setRequired(false);

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Получен' => TelegramDocumentStatus::Received,
                'Дубликат' => TelegramDocumentStatus::Duplicate,
                'В очереди' => TelegramDocumentStatus::Queued,
                'Обрабатывается' => TelegramDocumentStatus::Parsing,
                'Распарсен' => TelegramDocumentStatus::Parsed,
                'Требует проверки' => TelegramDocumentStatus::NeedsReview,
                'Подтвержден' => TelegramDocumentStatus::Confirmed,
                'Записан в таблицу' => TelegramDocumentStatus::Written,
                'Ошибка' => TelegramDocumentStatus::Failed,
                'Отменен' => TelegramDocumentStatus::Cancelled,
            ])
            ->formatValue(static fn (?TelegramDocumentStatus $value): string => $value?->label() ?? '')
            ->renderAsBadges();

        yield ChoiceField::new('source', 'Источник')
            ->setChoices([
                'Личное сообщение' => TelegramDocumentSource::DirectMessage,
                'Групповой чат' => TelegramDocumentSource::GroupChat,
                'Пересланное сообщение' => TelegramDocumentSource::ForwardedMessage,
                'Загрузка из админки' => TelegramDocumentSource::AdminUpload,
            ])
            ->formatValue(static fn (?TelegramDocumentSource $value): string => $value?->label() ?? '')
            ->hideOnIndex();

        yield TextField::new('chatId', 'Chat ID');

        yield TextField::new('messageId', 'Message ID')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('telegramFileId', 'Telegram File ID')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('telegramFileUniqueId', 'Telegram File Unique ID')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('mimeType', 'MIME')
            ->setRequired(false)
            ->hideOnIndex();

        yield NumberField::new('sizeBytes', 'Размер')
            ->hideOnIndex();

        yield TextField::new('checksumSha256', 'SHA-256')
            ->setRequired(false)
            ->hideOnIndex();

        yield NumberField::new('parserConfidence', 'Уверенность')
            ->hideOnForm();

        yield BooleanField::new('autoWriteAllowed', 'Автозапись разрешена')
            ->hideOnIndex();

        yield ArrayField::new('parsedFields', 'Извлеченные поля')
            ->hideOnIndex();

        yield ArrayField::new('validationErrors', 'Ошибки проверки')
            ->hideOnIndex();

        yield ArrayField::new('parserWarnings', 'Предупреждения парсера')
            ->hideOnIndex();

        yield TextareaField::new('rawText', 'Текст PDF')
            ->hideOnIndex()
            ->hideOnForm();

        yield TextareaField::new('errorMessage', 'Ошибка')
            ->hideOnIndex();

        yield DateTimeField::new('receivedAt', 'Получен')
            ->hideOnForm();

        yield DateTimeField::new('queuedAt', 'В очереди')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('parsedAt', 'Распарсен')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('writtenAt', 'Записан')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('failedAt', 'Ошибка в')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Создан')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Обновлен')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
