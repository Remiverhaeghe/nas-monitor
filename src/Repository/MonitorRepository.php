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

    /**
     * Groupes par ordre alphabétique, moniteurs sans groupe en dernier ; dans un groupe, du plus grave au moins grave.
     *
     * @return list<array{name: ?string, monitors: list<Monitor>}>
     */
    public function findAllGrouped(): array
    {
        $vMonitors = $this->findAll();
        usort($vMonitors, static fn (Monitor $pLeft, Monitor $pRight): int => [
            null === $pLeft->getGroupName(),
            mb_strtolower((string) $pLeft->getGroupName()),
            $pLeft->getStatus()->severity(),
            $pLeft->getName(),
        ] <=> [
            null === $pRight->getGroupName(),
            mb_strtolower((string) $pRight->getGroupName()),
            $pRight->getStatus()->severity(),
            $pRight->getName(),
        ]);

        $rGroups = [];
        foreach ($vMonitors as $vMonitor) {
            $vLast = array_key_last($rGroups);
            if (null === $vLast || $rGroups[$vLast]['name'] !== $vMonitor->getGroupName()) {
                $rGroups[] = ['name' => $vMonitor->getGroupName(), 'monitors' => []];
                $vLast = array_key_last($rGroups);
            }
            $rGroups[$vLast]['monitors'][] = $vMonitor;
        }

        return $rGroups;
    }
}
