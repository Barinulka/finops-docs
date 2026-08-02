<?php

namespace App\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType;

final class SheetWrittenFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(BooleanFilterType::class)
            ->setFormTypeOption('choices', [
                'Да' => true,
                'Нет' => false,
            ])
            ->setFormTypeOption('translation_domain', false);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto,
    ): void {
        if ($filterDataDto->getValue() === true) {
            $queryBuilder->andWhere(sprintf('%s.writtenAt IS NOT NULL', $filterDataDto->getEntityAlias()));

            return;
        }

        if ($filterDataDto->getValue() === false) {
            $queryBuilder->andWhere(sprintf('%s.writtenAt IS NULL', $filterDataDto->getEntityAlias()));
        }
    }
}
