<?php

namespace App\Repository;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\PingKind;
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

    /**
     * Pings de succès les plus récents, du plus ancien au plus récent pour les graphiques.
     *
     * @return list<Ping>
     */
    public function findRecentSuccessesForMonitor(Monitor $pMonitor, int $pLimit): array
    {
        $vPings = $this->createQueryBuilder('p')
            ->where('p.monitor = :monitor')
            ->andWhere('p.kind = :kind')
            ->setParameter('monitor', $pMonitor)
            ->setParameter('kind', PingKind::Success->value)
            ->orderBy('p.receivedAt', 'DESC')
            ->setMaxResults($pLimit)
            ->getQuery()
            ->getResult();
        $rPings = array_reverse($vPings);

        return $rPings;
    }

    /**
     * Retourne le nombre de pings supprimés.
     */
    public function deleteReceivedBefore(\DateTimeImmutable $pLimit): int
    {
        $rCount = (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.receivedAt < :limit')
            ->setParameter('limit', $pLimit)
            ->getQuery()
            ->execute();

        return $rCount;
    }
}
