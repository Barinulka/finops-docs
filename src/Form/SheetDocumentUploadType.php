<?php

namespace App\Form;

use App\Form\Model\SheetDocumentUploadData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SheetDocumentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', FileType::class, [
                'label' => 'PDF-файлы',
                'multiple' => true,
                'mapped' => true,
                'required' => true,
                'attr' => [
                    'accept' => 'application/pdf,.pdf',
                ],
                'help' => 'Можно выбрать до 20 PDF-файлов за один раз.',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Загрузить',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SheetDocumentUploadData::class,
        ]);
    }
}
