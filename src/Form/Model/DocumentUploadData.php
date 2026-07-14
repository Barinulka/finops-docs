<?php

namespace App\Form\Model;

use App\Entity\Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentUploadData
{
    #[Assert\NotNull(message: 'Выберите клиента.')]
    public ?Client $client = null;

    #[Assert\NotNull(message: 'Выберите PDF-файл.')]
    #[Assert\File(
        maxSize: '20M',
        mimeTypes: ['application/pdf', 'application/x-pdf'],
        mimeTypesMessage: 'Загрузите PDF-файл.'
    )]
    public ?UploadedFile $file = null;
}
