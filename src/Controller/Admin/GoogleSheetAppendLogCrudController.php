<?php

namespace App\Controller\Admin;

use App\Entity\GoogleSheetAppendLog;
use App\Enum\Telegram\GoogleSheetAppendStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
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
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->setSearchFields([
                'spreadsheetId',
                'sheetName',
                'appendedRange',
                'errorMessage',
                'sheetDocument.originalFilename',
                'requestedBy.email',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable('new', 'edit', 'delete')
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Статус')->setChoices([
                'Ожидает записи' => GoogleSheetAppendStatus::Pending,
                'Записано' => GoogleSheetAppendStatus::Success,
                'Ошибка' => GoogleSheetAppendStatus::Failed,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Дата попытки'))
            ->add(DateTimeFilter::new('writtenAt', 'Дата успешной записи'))
            ->add(EntityFilter::new('requestedBy', 'Кто отправил'))
            ->add(EntityFilter::new('sheetDocument', 'Документ'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield AssociationField::new('sheetDocument', 'Документ');

        yield AssociationField::new('requestedBy', 'Кто отправил');

        yield AssociationField::new('telegramDocument', 'Telegram документ')
            ->onlyOnDetail();

        yield ChoiceField::new('status', 'Статус')
            ->setChoices([
                'Ожидает записи' => GoogleSheetAppendStatus::Pending,
                'Записано' => GoogleSheetAppendStatus::Success,
                'Ошибка' => GoogleSheetAppendStatus::Failed,
            ])
            ->formatValue(static fn (?GoogleSheetAppendStatus $value): string => $value?->label() ?? '')
            ->renderAsBadges();

        yield TextField::new('sheetName', 'Лист');

        yield TextField::new('appendedRange', 'Диапазон')
            ->hideOnIndex();

        yield TextField::new('spreadsheetId', 'Spreadsheet ID')
            ->onlyOnDetail();

        yield ArrayField::new('payload', 'Payload')
            ->onlyOnDetail()
            ->setTemplatePath('admin/google_sheet_append_log/field/payload.html.twig');

        $errorMessageField = TextareaField::new('errorMessage', 'Ошибка')
            ->formatValue(static function (?string $value) use ($pageName): string {
                if ($value === null || $value === '') {
                    return '';
                }

                if ($pageName === Crud::PAGE_DETAIL) {
                    return $value;
                }

                return mb_strlen($value) > 180 ? mb_substr($value, 0, 180) . '...' : $value;
            });

        if ($pageName === Crud::PAGE_DETAIL) {
            $errorMessageField->setTemplatePath('admin/google_sheet_append_log/field/error_message.html.twig');
        }

        yield $errorMessageField;

        yield DateTimeField::new('writtenAt', 'Записано')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Создано')
            ->hideOnForm();
    }
}
