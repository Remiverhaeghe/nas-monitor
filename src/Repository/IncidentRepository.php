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
}
