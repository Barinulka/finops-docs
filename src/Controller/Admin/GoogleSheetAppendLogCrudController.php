<?php

namespace App\Controller\Admin;

use App\Entity\GoogleSheetAppendLog;
use App\Enum\Telegram\GoogleSheetAppendStatus;
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

final class GoogleSheetAppendLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GoogleSheetAppendLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Запись в Google Sheets')
            ->setEntityLabelInPlural('Записи в Google Sheets')
            ->setPageTitle(Crud::PAGE_INDEX, 'Google Sheets log')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Запись в Google Sheets')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['spreadsheetId', 'sheetName', 'appendedRange', 'errorMessage']);
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

        yield AssociationField::new('telegramDocument', 'Telegram документ');

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Ожидает записи' => GoogleSheetAppendStatus::Pending,
                'Записано' => GoogleSheetAppendStatus::Success,
                'Ошибка' => GoogleSheetAppendStatus::Failed,
            ])
            ->formatValue(static fn (?GoogleSheetAppendStatus $value): string => $value?->label() ?? '')
            ->renderAsBadges();

        yield TextField::new('spreadsheetId', 'Spreadsheet ID');

        yield TextField::new('sheetName', 'Лист');

        yield TextField::new('appendedRange', 'Диапазон')
            ->hideOnIndex();

        yield ArrayField::new('payload', 'Payload')
            ->hideOnIndex();

        yield TextareaField::new('errorMessage', 'Ошибка')
            ->hideOnIndex();

        yield DateTimeField::new('writtenAt', 'Записано')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Создано')
            ->hideOnForm();
    }
}
