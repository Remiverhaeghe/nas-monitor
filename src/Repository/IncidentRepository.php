<?php

namespace App\Repository;

use App\Entity\Incident;
use App\Entity\Monitor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Incident>
 */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Incident::class);
    }

    public function findOpenForMonitor(Monitor $pMonitor): ?Incident
    {
        $rIncident = $this->findOneBy(['monitor' => $pMonitor, 'resolvedAt' => null]);

        return $rIncident;
    }

    /**
     * @return array<int, Incident>
     */
    public function findOpenIndexedByMonitorId(): array
    {
        /** @var list<Incident> $vIncidents */
        $vIncidents = $this->createQueryBuilder('i')
            ->where('i.resolvedAt IS NULL')
            ->getQuery()
            ->getResult();

        $rIncidents = [];
        foreach ($vIncidents as $vIncident) {
            $rIncidents[(int) $vIncident->getMonitor()->getId()] = $vIncident;
        }

        return $rIncidents;
    }

    /**
     * Incidents ouverts jamais signalés, ou signalés pour la dernière fois au plus tard à la date donnée.
     *
     * @return list<Incident>
     */
    public function findOpenToNotify(?\DateTimeImmutable $pNotifiedBefore): array
    {
        $vQueryBuilder = $this->createQueryBuilder('i')
            ->join('i.monitor', 'm')
            ->where('i.resolvedAt IS NULL')
            ->andWhere('m.paused = false');

        if (null === $pNotifiedBefore) {
            $vQueryBuilder->andWhere('i.lastNotifiedAt IS NULL');
        } else {
            $vQueryBuilder
                ->andWhere('(i.lastNotifiedAt IS NULL OR i.lastNotifiedAt <= :before)')
                ->setParameter('before', $pNotifiedBefore);
        }

        $rIncidents = $vQueryBuilder->getQuery()->getResult();

        return $rIncidents;
    }

    /**
     * @return list<Incident>
     */
    public function findLatestForMonitor(Monitor $pMonitor, int $pLimit): array
    {
        $rIncidents = $this->createQueryBuilder('i')
            ->where('i.monitor = :monitor')
            ->setParameter('monitor', $pMonitor)
            ->orderBy('i.startedAt', 'DESC')
            ->setMaxResults($pLimit)
            ->getQuery()
            ->getResult();

        return $rIncidents;
    }

    /**
     * Incidents dont une partie se situe dans la période, y compris ceux encore ouverts.
     *
     * @return list<Incident>
     */
    public function findOverlapping(\DateTimeImmutable $pFrom, \DateTimeImmutable $pTo): array
    {
        /** @var list<Incident> $rIncidents */
        $rIncidents = $this->createQueryBuilder('i')
            ->where('i.startedAt < :to')
            ->andWhere('i.resolvedAt IS NULL OR i.resolvedAt > :from')
            ->setParameter('from', $pFrom)
            ->setParameter('to', $pTo)
            ->getQuery()
            ->getResult();

        return $rIncidents;
    }
}
