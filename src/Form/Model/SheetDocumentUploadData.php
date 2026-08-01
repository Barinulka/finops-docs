<?php

namespace App\Form\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class SheetDocumentUploadData
{
    /**
     * @var list<UploadedFile>
     */
    #[Assert\NotBlank(message: 'Выберите хотя бы один PDF-файл.')]
    #[Assert\Count(
        min: 1,
        max: 20,
        minMessage: 'Выберите хотя бы один PDF-файл.',
        maxMessage: 'За один раз можно загрузить не больше 20 файлов.',
    )]
    #[Assert\All([
        new Assert\File(
            maxSize: '50M',
            mimeTypes: ['application/pdf'],
            maxSizeMessage: 'PDF-файл не должен быть больше 50 МБ.',
            mimeTypesMessage: 'Можно загружать только PDF-файлы.',
        ),
    ])]
    public array $files = [];
}
