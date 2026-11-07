<?php

namespace App\Repository;

use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MaintenanceWindow>
 */
class MaintenanceWindowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, MaintenanceWindow::class);
    }

    public function isActiveFor(Monitor $pMonitor, \DateTimeImmutable $pNow): bool
    {
        $vCount = (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.startsAt <= :now')
            ->andWhere('w.endsAt > :now')
            ->andWhere('(w.monitor IS NULL OR w.monitor = :monitor)')
            ->setParameter('now', $pNow)
            ->setParameter('monitor', $pMonitor)
            ->getQuery()
            ->getSingleScalarResult();
        $rActive = $vCount > 0;

        return $rActive;
    }

    /**
     * Plages en cours et à venir, de la plus proche à la plus lointaine.
     *
     * @return list<MaintenanceWindow>
     */
    public function findNotEnded(\DateTimeImmutable $pNow): array
    {
        $rWindows = $this->createQueryBuilder('w')
            ->where('w.endsAt > :now')
            ->setParameter('now', $pNow)
            ->orderBy('w.startsAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rWindows;
    }
}
