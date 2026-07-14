<?php

namespace App\Repository;

use App\Entity\TelegramUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class TelegramUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramUser::class);
    }

    public function findOneByTelegramId(string|int $telegramId): ?TelegramUser
    {
        return $this->findOneBy([
            'telegramId' => (string) $telegramId,
        ]);
    }
}
