<?php

namespace App\Tests\Service;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\MonitorChecker;
use App\Service\PingRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class MonitorCheckerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const INTERVAL = 3600;
    private const GRACE = 600;

    private EntityManagerInterface $aEntityManager;
    private ClockInterface $aClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->aClock = self::mockTime('2026-11-07 12:00:00');
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->aEntityManager->createQuery('DELETE FROM '.Incident::class)->execute();
        $this->aEntityManager->createQuery('DELETE FROM '.Ping::class)->execute();
        $this->aEntityManager->createQuery('DELETE FROM '.Monitor::class)->execute();
    }

    public function testMonitorWithinGraceIsLateWithoutAlert(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();
        $this->aClock->sleep(self::INTERVAL + 1);

        $this->check();

        self::assertSame(MonitorStatus::Late, $vMonitor->getStatus());
        self::assertNull($this->findOpenIncident($vMonitor));
        self::assertQueuedEmailCount(0);
    }

    public function testMissedPingOpensIncidentAndSendsAlertOnce(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();
        $this->aClock->sleep(self::INTERVAL + self::GRACE + 1);

        self::assertSame(1, $this->check());
        self::assertSame(0, $this->check());

        self::assertSame(MonitorStatus::Down, $vMonitor->getStatus());
        self::assertSame(IncidentCause::Missed, $this->findOpenIncident($vMonitor)?->getCause());
        self::assertSame(1, $this->aEntityManager->getRepository(Incident::class)->count([]));
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'En panne : Sauvegarde restic');
    }

    public function testFailPingOpensIncidentWithFailedCause(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();

        $this->getPingRecorder()->record($vMonitor, PingKind::Fail, null, null);

        self::assertSame(IncidentCause::Failed, $this->findOpenIncident($vMonitor)?->getCause());
        self::assertQueuedEmailCount(1);
    }

    public function testSuccessPingResolvesIncidentAndSendsRecovery(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();
        $this->aClock->sleep(self::INTERVAL + self::GRACE + 1);
        $this->check();
        $vIncident = $this->findOpenIncident($vMonitor);
        $this->aClock->sleep(1800);

        $this->getPingRecorder()->record($vMonitor, PingKind::Success, null, null);

        self::assertSame(MonitorStatus::Up, $vMonitor->getStatus());
        self::assertNotNull($vIncident);
        self::assertFalse($vIncident->isOpen());
        self::assertQueuedEmailCount(2);
        self::assertEmailSubjectContains(self::getMailerMessage(1), 'Rétabli : Sauvegarde restic');
    }

    private function createMonitorWithSuccessPing(): Monitor
    {
        $rMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, self::INTERVAL, self::GRACE, $this->aClock->now());
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();
        $this->getPingRecorder()->record($rMonitor, PingKind::Success, null, null);

        return $rMonitor;
    }

    private function check(): int
    {
        $rChangedCount = static::getContainer()->get(MonitorChecker::class)->check();

        return $rChangedCount;
    }

    private function findOpenIncident(Monitor $pMonitor): ?Incident
    {
        $rIncident = $this->aEntityManager->getRepository(Incident::class)->findOneBy(['monitor' => $pMonitor, 'resolvedAt' => null]);

        return $rIncident;
    }

    private function getPingRecorder(): PingRecorder
    {
        $rPingRecorder = static::getContainer()->get(PingRecorder::class);

        return $rPingRecorder;
    }
}
