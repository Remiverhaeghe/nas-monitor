<?php

namespace App\Repository;

use App\Entity\Monitor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Monitor>
 */
class MonitorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Monitor::class);
    }

    public function findOneByPingToken(string $pToken): ?Monitor
    {
        $rMonitor = $this->findOneBy(['pingTokenHash' => Monitor::hashToken($pToken)]);

        return $rMonitor;
    }
}
