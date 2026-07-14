<?php

namespace App\Repository;

use App\Entity\TelegramDocument;
use App\Entity\TelegramUser;
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

    /**
     * @return list<TelegramDocument>
     */
    public function findRecentForUser(TelegramUser $telegramUser, int $limit = 10): array
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.telegramUser = :telegramUser')
            ->setParameter('telegramUser', $telegramUser)
            ->orderBy('document.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return list<TelegramDocument>
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('document')
            ->addSelect('telegramUser')
            ->leftJoin('document.telegramUser', 'telegramUser')
            ->orderBy('document.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
            ;
    }
}
