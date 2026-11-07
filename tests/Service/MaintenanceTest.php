<?php

namespace App\Tests\Service;

use App\Dto\PingPayload;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\NotificationChannel;
use App\Enum\PingKind;
use App\Notification\NtfyMessage;
use App\Scheduler\PurgePingsHandler;
use App\Scheduler\PurgePingsMessage;
use App\Service\PingRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class MaintenanceTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-07 12:00:00');
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
    }

    public function testConcurrentUpdateIsDetected(): void
    {
        $vMonitor = $this->createMonitor(NotificationChannel::Email);
        $this->aEntityManager->getConnection()->executeStatement('UPDATE monitor SET version = version + 1 WHERE id = ?', [$vMonitor->getId()]);

        $this->expectException(OptimisticLockException::class);
        static::getContainer()->get(PingRecorder::class)->record($vMonitor, PingKind::Success, null, null);
    }

    public function testPurgeKeepsRecentPings(): void
    {
        $vMonitor = $this->createMonitor(NotificationChannel::Email);
        $vNow = new \DateTimeImmutable('2026-11-07 12:00:00');
        $this->aEntityManager->persist(new Ping($vMonitor, PingKind::Success, $vNow->modify('-91 days')));
        $this->aEntityManager->persist(new Ping($vMonitor, PingKind::Success, $vNow->modify('-89 days')));
        $this->aEntityManager->flush();

        static::getContainer()->get(PurgePingsHandler::class)(new PurgePingsMessage());

        self::assertSame(1, $this->aEntityManager->getRepository(Ping::class)->count([]));
    }

    public function testAnomalyIsRecordedAndAlertedOncePerSeries(): void
    {
        $vMonitor = $this->createMonitor(NotificationChannel::Email);
        $vRecorder = static::getContainer()->get(PingRecorder::class);
        $vClock = self::mockTime('2026-11-07 12:00:00');

        for ($vIndex = 0; $vIndex < 6; ++$vIndex) {
            $vClock->sleep(3600);
            $vRecorder->record($vMonitor, PingKind::Success, $this->payload(1000000000), null);
        }
        $vClock->sleep(3600);
        $vFirst = $vRecorder->record($vMonitor, PingKind::Success, $this->payload(100000000), null);
        $vClock->sleep(3600);
        $vSecond = $vRecorder->record($vMonitor, PingKind::Success, $this->payload(100000000), null);

        self::assertNotNull($vFirst->getAnomaly());
        self::assertNotNull($vSecond->getAnomaly());
        self::assertSame(MonitorStatus::Up, $vMonitor->getStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'Anomalie : Sauvegarde restic');
    }

    public function testNtfyChannelSendsNoEmail(): void
    {
        $vMonitor = $this->createMonitor(NotificationChannel::Ntfy);

        static::getContainer()->get(PingRecorder::class)->record($vMonitor, PingKind::Fail, null, null);

        self::assertQueuedEmailCount(0);
        $vTransport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $vTransport);
        $vMessages = array_map(static fn ($pEnvelope): object => $pEnvelope->getMessage(), $vTransport->getSent());
        self::assertCount(1, $vMessages);
        self::assertInstanceOf(NtfyMessage::class, $vMessages[0]);
        self::assertTrue($vMessages[0]->urgent);
    }

    private function payload(int $pSizeBytes): PingPayload
    {
        $rPayload = new PingPayload();
        $rPayload->durationMs = 60000;
        $rPayload->sizeBytes = $pSizeBytes;

        return $rPayload;
    }

    private function createMonitor(NotificationChannel $pChannel): Monitor
    {
        $rMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-11-07 11:00:00'));
        $rMonitor->setNotificationChannel($pChannel);
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }
}
