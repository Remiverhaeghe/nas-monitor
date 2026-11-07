<?php

namespace App\Repository;

use App\Entity\Monitor;
use App\Entity\Ping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ping>
 */
class PingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Ping::class);
    }

    /**
     * @return list<Ping>
     */
    public function findLatestForMonitor(Monitor $pMonitor, int $pLimit): array
    {
        $rPings = $this->createQueryBuilder('p')
            ->where('p.monitor = :monitor')
            ->setParameter('monitor', $pMonitor)
            ->orderBy('p.receivedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($pLimit)
            ->getQuery()
            ->getResult();

        return $rPings;
    }
}
