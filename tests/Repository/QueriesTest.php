<?php

namespace App\Tests\Repository;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QueriesTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class, User::class] as $vClass) {
            $this->vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }
    }

    private function persistMonitor(string $pName, MonitorType $pType): Monitor
    {
        $vMonitor = new Monitor($pName, $pType, 3600, 600, new \DateTimeImmutable('2026-11-01 08:00:00'));
        $this->vEntityManager->persist($vMonitor);
        $this->vEntityManager->flush();

        return $vMonitor;
    }

    public function testOpenIncidentsAreIndexedByMonitorId(): void
    {
        $vMonitor = $this->persistMonitor('NAS', MonitorType::Heartbeat);
        $this->vEntityManager->persist(new Incident($vMonitor, IncidentCause::Missed, new \DateTimeImmutable('2026-11-10 10:00:00')));
        $this->vEntityManager->flush();

        $vIndexed = static::getContainer()->get(IncidentRepository::class)->findOpenIndexedByMonitorId();

        self::assertSame([(int) $vMonitor->getId()], array_keys($vIndexed));
    }

    public function testOpenIncidentsNeverNotifiedAreListedWithoutDateLimit(): void
    {
        $vMonitor = $this->persistMonitor('NAS', MonitorType::Heartbeat);
        $this->vEntityManager->persist(new Incident($vMonitor, IncidentCause::Missed, new \DateTimeImmutable('2026-11-10 10:00:00')));
        $this->vEntityManager->flush();

        $vIncidents = static::getContainer()->get(IncidentRepository::class)->findOpenToNotify(null);

        self::assertCount(1, $vIncidents);
    }

    public function testActiveChecksExcludeHeartbeatMonitors(): void
    {
        $this->persistMonitor('Sauvegarde', MonitorType::Heartbeat);
        $vTcp = $this->persistMonitor('Port 443', MonitorType::Tcp);

        $vMonitors = static::getContainer()->get(MonitorRepository::class)->findActiveChecks();

        self::assertSame([$vTcp->getId()], array_map(static fn (Monitor $pMonitor): ?int => $pMonitor->getId(), $vMonitors));
    }
}
