<?php

namespace App\Repository;

use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Enum\MaintenanceRecurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les plages récurrentes sont filtrées en PHP : leurs occurrences dépendent du fuseau d'affichage.
 *
 * @extends ServiceEntityRepository<MaintenanceWindow>
 */
class MaintenanceWindowRepository extends ServiceEntityRepository
{
    private \DateTimeZone $aTimezone;

    public function __construct(
        ManagerRegistry $pRegistry,
        #[Autowire(param: 'app.display_timezone')]
        string $pDisplayTimezone,
    ) {
        parent::__construct($pRegistry, MaintenanceWindow::class);
        $this->aTimezone = new \DateTimeZone($pDisplayTimezone);
    }

    public function isActiveFor(Monitor $pMonitor, \DateTimeImmutable $pNow): bool
    {
        /** @var list<MaintenanceWindow> $vWindows */
        $vWindows = $this->createStartedQuery($pNow)
            ->andWhere('(w.monitor IS NULL OR w.monitor = :monitor)')
            ->setParameter('monitor', $pMonitor)
            ->getQuery()
            ->getResult();

        $rActive = [] !== array_filter($vWindows, fn (MaintenanceWindow $pWindow): bool => $pWindow->isActive($pNow, $this->aTimezone));

        return $rActive;
    }

    /**
     * @return list<MaintenanceWindow>
     */
    public function findActive(\DateTimeImmutable $pNow): array
    {
        /** @var list<MaintenanceWindow> $vWindows */
        $vWindows = $this->createStartedQuery($pNow)->getQuery()->getResult();

        $rWindows = array_values(array_filter($vWindows, fn (MaintenanceWindow $pWindow): bool => $pWindow->isActive($pNow, $this->aTimezone)));

        return $rWindows;
    }

    /**
     * Plages en cours et à venir, de la plus proche à la plus lointaine.
     *
     * @return list<MaintenanceWindow>
     */
    public function findNotEnded(\DateTimeImmutable $pNow): array
    {
        /** @var list<MaintenanceWindow> $rWindows */
        $rWindows = $this->createQueryBuilder('w')
            ->where('w.endsAt > :now OR w.recurrence <> :none')
            ->setParameter('now', $pNow)
            ->setParameter('none', MaintenanceRecurrence::None)
            ->getQuery()
            ->getResult();
        usort($rWindows, fn (MaintenanceWindow $pLeft, MaintenanceWindow $pRight): int => $pLeft->findOccurrenceStart($pNow, $this->aTimezone)
            <=> $pRight->findOccurrenceStart($pNow, $this->aTimezone));

        return $rWindows;
    }

    private function createStartedQuery(\DateTimeImmutable $pNow): QueryBuilder
    {
        $rQueryBuilder = $this->createQueryBuilder('w')
            ->where('w.startsAt <= :now')
            ->andWhere('(w.endsAt > :now OR w.recurrence <> :none)')
            ->setParameter('now', $pNow)
            ->setParameter('none', MaintenanceRecurrence::None);

        return $rQueryBuilder;
    }
}
