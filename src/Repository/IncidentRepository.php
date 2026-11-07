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
}
