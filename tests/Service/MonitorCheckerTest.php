<?php

namespace App\Tests\Service;

use App\Dto\PingPayload;
use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\IncidentReminder;
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

    public function testNoAlertDuringMaintenanceThenAlertWhenItEnds(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();
        $this->aEntityManager->persist(new MaintenanceWindow($vMonitor, $this->aClock->now(), $this->aClock->now()->modify('+2 hours'), 'Mise à jour du NAS'));
        $this->aEntityManager->flush();
        $this->aClock->sleep(self::INTERVAL + self::GRACE + 1);

        $this->check();

        self::assertSame(MonitorStatus::Down, $vMonitor->getStatus());
        self::assertNull($this->findOpenIncident($vMonitor)?->getLastNotifiedAt());
        self::assertQueuedEmailCount(0);

        $this->aClock->sleep(3600);
        $this->remind();

        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'En panne : Sauvegarde restic');
    }

    public function testLongOutageIsRemindedEverySixHours(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing();
        $this->aClock->sleep(self::INTERVAL + self::GRACE + 1);
        $this->check();

        $this->aClock->sleep(5 * 3600);
        self::assertSame(0, $this->remind());

        $this->aClock->sleep(3600);
        self::assertSame(1, $this->remind());
        self::assertQueuedEmailCount(2);
        self::assertEmailSubjectContains(self::getMailerMessage(1), 'Toujours en panne : Sauvegarde restic');
        self::assertSame(0, $this->remind());
    }

    public function testChildAlertWaitsWhileParentIsDown(): void
    {
        $vChild = $this->createMonitorWithSuccessPing('Partage SMB');
        $vParent = $this->createMonitorWithSuccessPing('NAS');
        $vChild->setParent($vParent);
        $this->aEntityManager->flush();
        $this->aClock->sleep(self::INTERVAL + self::GRACE + 1);

        $this->check();

        self::assertSame(MonitorStatus::Down, $vChild->getStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'En panne : NAS');

        $this->getPingRecorder()->record($vParent, PingKind::Success, null, null);
        $this->remind();

        self::assertQueuedEmailCount(3);
        self::assertEmailSubjectContains(self::getMailerMessage(1), 'Rétabli : NAS');
        self::assertEmailSubjectContains(self::getMailerMessage(2), 'En panne : Partage SMB');
    }

    public function testMetricAboveLimitIsAnAnomaly(): void
    {
        $vMonitor = $this->createMonitorWithSuccessPing('Relevé du NAS');
        $vMonitor->setMetricLimits(['disk_used_percent' => 90.0]);
        $this->aEntityManager->flush();

        $vPayload = new PingPayload();
        $vPayload->metrics = ['disk_used_percent' => 93, 'temperature_c' => 41.5];
        $vPing = $this->getPingRecorder()->record($vMonitor, PingKind::Success, $vPayload, null);

        self::assertSame(['disk_used_percent' => 93.0, 'temperature_c' => 41.5], $vPing->getMetrics());
        self::assertSame('disk_used_percent à 93 (maximum 90)', $vPing->getAnomaly());
        self::assertSame(MonitorStatus::Up, $vMonitor->getStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'Anomalie : Relevé du NAS');
    }

    private function remind(): int
    {
        $rCount = static::getContainer()->get(IncidentReminder::class)->remind();

        return $rCount;
    }

    private function createMonitorWithSuccessPing(string $pName = 'Sauvegarde restic'): Monitor
    {
        $rMonitor = new Monitor($pName, MonitorType::Heartbeat, self::INTERVAL, self::GRACE, $this->aClock->now());
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
