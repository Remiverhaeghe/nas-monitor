<?php

namespace App\Tests\Service;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\IncidentReminder;
use App\Service\MonitorChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QuietPathsTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $this->vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }
    }

    public function testHealthyMonitorKeepsItsStatus(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow->modify('-1 day'));
        $vMonitor->recordPing(PingKind::Success, $vNow);
        $this->vEntityManager->persist($vMonitor);
        $this->vEntityManager->flush();

        self::assertSame(0, static::getContainer()->get(MonitorChecker::class)->check());
    }

    public function testReminderIsWithheldDuringMaintenance(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow->modify('-1 day'));
        $this->vEntityManager->persist($vMonitor);
        $this->vEntityManager->persist(new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-2 hours')));
        $this->vEntityManager->persist(new MaintenanceWindow(null, $vNow->modify('-1 hour'), $vNow->modify('+1 hour'), 'Mise à jour'));
        $this->vEntityManager->flush();

        self::assertSame(0, static::getContainer()->get(IncidentReminder::class)->remind());
    }
}
