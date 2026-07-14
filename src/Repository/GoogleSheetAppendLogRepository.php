<?php

namespace App\Repository;

use App\Entity\GoogleSheetAppendLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class GoogleSheetAppendLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleSheetAppendLog::class);
    }
}
