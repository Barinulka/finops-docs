<?php

namespace App\Repository;

use App\Entity\TelegramDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class TelegramDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramDocument::class);
    }

    public function findOneByChecksum(string $checksumSha256): ?TelegramDocument
    {
        return $this->findOneBy([
            'checksumSha256' => $checksumSha256,
        ]);
    }

    public function findOneByTelegramFileUniqueId(string $fileUniqueId): ?TelegramDocument
    {
        return $this->findOneBy([
            'telegramFileUniqueId' => $fileUniqueId,
        ]);
    }
}
