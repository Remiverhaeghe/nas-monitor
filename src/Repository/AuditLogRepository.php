<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, AuditLog::class);
    }

    /**
     * @return list<AuditLog>
     */
    public function findLatest(int $pLimit): array
    {
        /** @var list<AuditLog> $rEntries */
        $rEntries = $this->createQueryBuilder('a')
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($pLimit)
            ->getQuery()
            ->getResult();

        return $rEntries;
    }

    public function deleteOccurredBefore(\DateTimeImmutable $pLimit): int
    {
        $rCount = (int) $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :limit')
            ->setParameter('limit', $pLimit)
            ->getQuery()
            ->execute();

        return $rCount;
    }
}
