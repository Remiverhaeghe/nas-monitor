<?php

namespace App\Repository;

use App\Entity\Monitor;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
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

    /**
     * @return list<Monitor>
     */
    public function findActiveChecks(): array
    {
        $rMonitors = $this->createQueryBuilder('m')
            ->where('m.paused = false')
            ->andWhere('m.type <> :heartbeat')
            ->setParameter('heartbeat', MonitorType::Heartbeat->value)
            ->getQuery()
            ->getResult();

        return $rMonitors;
    }

    /**
     * Moniteurs dont le statut peut changer avec le temps, sans nouveau ping.
     *
     * @return list<Monitor>
     */
    public function findTimeSensitive(): array
    {
        $rMonitors = $this->createQueryBuilder('m')
            ->where('m.paused = false')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('statuses', [MonitorStatus::Up->value, MonitorStatus::Late->value])
            ->getQuery()
            ->getResult();

        return $rMonitors;
    }
}
