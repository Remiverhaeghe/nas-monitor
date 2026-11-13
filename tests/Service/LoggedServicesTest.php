<?php

namespace App\Tests\Service;

use App\Check\ActiveChecker;
use App\Check\ActiveCheckRunner;
use App\Dto\PingPayload;
use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Notification\AlertNotifier;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use App\Service\AnomalyDetector;
use App\Service\AvailabilityCalculator;
use App\Service\IncidentManager;
use App\Service\IncidentReminder;
use App\Service\MonitorChecker;
use App\Service\PingRecorder;
use App\Tests\Support\RecordingLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * Journaux, ordre de traitement et alertes : chaque message et chaque contexte est comparé exactement.
 */
class LoggedServicesTest extends KernelTestCase
{
    private EntityManagerInterface $aEntityManager;
    private MockClock $aClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->aClock = new MockClock('2026-11-11 12:00:00');
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }
    }

    private function recorder(RecordingLogger $pLogger): PingRecorder
    {
        $vContainer = static::getContainer();

        return new PingRecorder(
            $this->aEntityManager,
            $this->aClock,
            $vContainer->get(IncidentManager::class),
            new AnomalyDetector(50, 3),
            $vContainer->get(PingRepository::class),
            $vContainer->get(AlertNotifier::class),
            $pLogger,
        );
    }

    private function heartbeat(string $pName = 'Sauvegarde', int $pInterval = 3600): Monitor
    {
        $rMonitor = new Monitor($pName, MonitorType::Heartbeat, $pInterval, 600, $this->aClock->now());
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }

    private function payload(int $pSize): PingPayload
    {
        $rPayload = new PingPayload();
        $rPayload->sizeBytes = $pSize;
        $rPayload->durationMs = 1000;

        return $rPayload;
    }

    private function withHistory(Monitor $pMonitor): void
    {
        $vRecorder = $this->recorder(new RecordingLogger());
        for ($vIndex = 0; $vIndex < 5; ++$vIndex) {
            $this->aClock->sleep(60);
            $vRecorder->record($pMonitor, PingKind::Success, $this->payload(1_000_000), null);
        }
    }

    public function testAnomalyIsLoggedAndAlertedOnlyAtTheStartOfASeries(): void
    {
        $vMonitor = $this->heartbeat();
        $this->withHistory($vMonitor);
        $vLogger = new RecordingLogger();
        $this->aClock->sleep(60);

        $vPing = $this->recorder($vLogger)->record($vMonitor, PingKind::Success, $this->payload(100), null);

        self::assertNotNull($vPing->getAnomaly());
        self::assertSame(
            [['level' => 'warning', 'message' => 'Anomalie sur le moniteur {monitor} : {anomaly}', 'context' => ['monitor' => $vMonitor->getId(), 'anomaly' => $vPing->getAnomaly()]]],
            $vLogger->withLevel('warning'),
        );
        self::assertSame(
            [['level' => 'info', 'message' => 'Ping {kind} reçu pour le moniteur {monitor}', 'context' => ['kind' => 'success', 'monitor' => $vMonitor->getId()]]],
            $vLogger->withLevel('info'),
        );
        self::assertQueuedEmailCount(1);

        $this->aClock->sleep(60);
        $this->recorder(new RecordingLogger())->record($vMonitor, PingKind::Success, $this->payload(100), null);
        self::assertQueuedEmailCount(1);
    }

    public function testFailedPingNeverCountsAsAnAnomaly(): void
    {
        $vMonitor = $this->heartbeat();
        $this->withHistory($vMonitor);

        $vPing = $this->recorder(new RecordingLogger())->record($vMonitor, PingKind::Fail, $this->payload(100), null);

        self::assertNull($vPing->getAnomaly());
    }

    public function testStatusChangeIsLogged(): void
    {
        $vMonitor = $this->heartbeat();
        $this->recorder(new RecordingLogger())->record($vMonitor, PingKind::Success, null, null);
        $vPrevious = $vMonitor->getStatus()->value;
        $vLogger = new RecordingLogger();

        $this->recorder($vLogger)->record($vMonitor, PingKind::Fail, null, null);

        self::assertSame(
            [['level' => 'notice', 'message' => 'Moniteur {monitor} : {previous} -> {status}', 'context' => ['monitor' => $vMonitor->getId(), 'previous' => $vPrevious, 'status' => $vMonitor->getStatus()->value]]],
            $vLogger->withLevel('notice'),
        );
    }

    public function testMetricAnomalyWithoutHistoryIsCutAtTwoHundredFiftyFiveCharacters(): void
    {
        $vMonitor = $this->heartbeat();
        $vLimits = [];
        $vMetrics = [];
        for ($vIndex = 0; $vIndex < 60; ++$vIndex) {
            $vName = \sprintf('température_du_disque_%02d', $vIndex);
            $vLimits[$vName] = 10.0;
            $vMetrics[$vName] = 99.0;
        }
        $vMonitor->setMetricLimits($vLimits);
        $vPayload = new PingPayload();
        $vPayload->metrics = $vMetrics;

        $vLogger = new RecordingLogger();
        $vPing = $this->recorder($vLogger)->record($vMonitor, PingKind::Success, $vPayload, null);

        self::assertSame(255, mb_strlen((string) $vPing->getAnomaly()));
        self::assertSame(255, mb_strlen($vLogger->withLevel('warning')[0]['context']['anomaly']));
    }

    public function testCheckerKeepsGoingAfterAnUnchangedMonitorAndLogsTheChange(): void
    {
        $vClock = $this->aClock;
        $vSteady = $this->heartbeat('Stable', 604800);
        $vLate = $this->heartbeat('En retard');
        foreach ([$vSteady, $vLate] as $vMonitor) {
            $this->recorder(new RecordingLogger())->record($vMonitor, PingKind::Success, null, null);
        }
        $vClock->sleep(7200);
        $vPrevious = $vLate->getStatus()->value;
        $vLogger = new RecordingLogger();
        $vContainer = static::getContainer();

        $vChanged = (new MonitorChecker(
            $vContainer->get(MonitorRepository::class),
            $vContainer->get(IncidentManager::class),
            $this->aEntityManager,
            $vClock,
            $vLogger,
        ))->check();

        self::assertSame(1, $vChanged);
        self::assertSame(
            [['level' => 'notice', 'message' => 'Moniteur {monitor} : {previous} -> {status}', 'context' => ['monitor' => $vLate->getId(), 'previous' => $vPrevious, 'status' => $vLate->getStatus()->value]]],
            $vLogger->withLevel('notice'),
        );
    }

    public function testActiveRunnerSkipsDueChecksOnlyForRecentOnesAndLogsFailures(): void
    {
        $vNow = new \DateTimeImmutable();
        $vOpenServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vOpenServer);
        $vOpen = (string) stream_socket_get_name($vOpenServer, false);
        $vClosedServer = stream_socket_server('tcp://127.0.0.1:0');
        $vClosed = (string) stream_socket_get_name($vClosedServer, false);
        fclose($vClosedServer);

        $vNotDue = new Monitor('Récent', MonitorType::Tcp, 3600, 60, $vNow->modify('-1 day'));
        $vNotDue->setTarget($vOpen);
        $vNotDue->recordPing(PingKind::Success, $vNow);
        $vUp = new Monitor('Ouvert', MonitorType::Tcp, 60, 60, $vNow->modify('-1 day'));
        $vUp->setTarget($vOpen);
        $vDown = new Monitor('Fermé', MonitorType::Tcp, 60, 60, $vNow->modify('-1 day'));
        $vDown->setTarget($vClosed);
        foreach ([$vNotDue, $vUp, $vDown] as $vMonitor) {
            $this->aEntityManager->persist($vMonitor);
        }
        $this->aEntityManager->flush();
        $vLogger = new RecordingLogger();
        $vContainer = static::getContainer();

        $vCount = (new ActiveCheckRunner(
            $vContainer->get(MonitorRepository::class),
            $vContainer->get(ActiveChecker::class),
            $vContainer->get(PingRecorder::class),
            new NativeClock(),
            $vLogger,
        ))->runDueChecks();

        self::assertSame(2, $vCount);
        $vKind = static fn (EntityManagerInterface $pManager, Monitor $pMonitor): mixed => $pManager->getConnection()
            ->fetchOne('SELECT kind FROM ping WHERE monitor_id = ?', [$pMonitor->getId()]);
        self::assertSame('success', $vKind($this->aEntityManager, $vUp));
        self::assertSame('fail', $vKind($this->aEntityManager, $vDown));

        $vWarnings = $vLogger->withLevel('warning');
        self::assertCount(1, $vWarnings);
        self::assertSame('Contrôle en échec pour le moniteur {monitor} : {message}', $vWarnings[0]['message']);
        self::assertSame(['monitor', 'message'], array_keys($vWarnings[0]['context']));
        self::assertSame($vDown->getId(), $vWarnings[0]['context']['monitor']);
        self::assertStringContainsString('Connexion refusée', $vWarnings[0]['context']['message']);
        fclose($vOpenServer);
    }

    public function testReminderLogsEachNotifiedIncident(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = $this->heartbeat();
        $vIncident = new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-1 hour'));
        $this->aEntityManager->persist($vIncident);
        $this->aEntityManager->flush();
        $vLogger = new RecordingLogger();
        $vContainer = static::getContainer();

        $vSent = (new IncidentReminder(
            $vContainer->get(IncidentRepository::class),
            $vContainer->get(AlertNotifier::class),
            $this->aEntityManager,
            new NativeClock(),
            $vLogger,
            4,
        ))->remind();

        self::assertSame(1, $vSent);
        self::assertSame(
            [['level' => 'notice', 'message' => 'Rappel envoyé pour l\'incident {incident}', 'context' => ['incident' => $vIncident->getId()]]],
            $vLogger->withLevel('notice'),
        );
    }

    public function testRemindersAreDisabledWhenTheDelayIsZero(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = $this->heartbeat();
        $vIncident = new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-5 hours'));
        $vIncident->markNotified($vNow->modify('-1 hour'));
        $this->aEntityManager->persist($vIncident);
        $this->aEntityManager->flush();
        $vContainer = static::getContainer();

        $vSent = (new IncidentReminder(
            $vContainer->get(IncidentRepository::class),
            $vContainer->get(AlertNotifier::class),
            $this->aEntityManager,
            new NativeClock(),
            new RecordingLogger(),
            0,
        ))->remind();

        self::assertSame(0, $vSent);
    }

    public function testReminderKeepsGoingAfterAHeldBackAlert(): void
    {
        $vNow = new \DateTimeImmutable();
        $vHeld = $this->heartbeat('En maintenance');
        $vOther = $this->heartbeat('Normal');
        $this->aEntityManager->persist(new MaintenanceWindow($vHeld, $vNow->modify('-1 hour'), $vNow->modify('+1 hour'), 'Mise à jour'));
        foreach ([$vHeld, $vOther] as $vMonitor) {
            $this->aEntityManager->persist(new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-1 hour')));
        }
        $this->aEntityManager->flush();
        $vContainer = static::getContainer();

        $vSent = (new IncidentReminder(
            $vContainer->get(IncidentRepository::class),
            $vContainer->get(AlertNotifier::class),
            $this->aEntityManager,
            new NativeClock(),
            new RecordingLogger(),
            4,
        ))->remind();

        self::assertSame(1, $vSent);
    }

    public function testIncidentOpeningAndResolutionAreLogged(): void
    {
        $vMonitor = $this->heartbeat();
        $vLogger = new RecordingLogger();
        $vContainer = static::getContainer();
        $vManager = new IncidentManager(
            $vContainer->get(IncidentRepository::class),
            $this->aEntityManager,
            $vContainer->get(AlertNotifier::class),
            $vLogger,
        );
        $vStart = $this->aClock->now();

        $vMonitor->recordPing(PingKind::Fail, $vStart);
        $vManager->handleStatusChange($vMonitor, $vStart);
        $this->aEntityManager->flush();
        $vEnd = $vStart->modify('+10 minutes');
        $vMonitor->recordPing(PingKind::Success, $vEnd);
        $vManager->handleStatusChange($vMonitor, $vEnd);

        self::assertSame(
            [['level' => 'warning', 'message' => 'Incident ouvert pour le moniteur {monitor} ({cause})', 'context' => ['monitor' => $vMonitor->getId(), 'cause' => 'failed']]],
            $vLogger->withLevel('warning'),
        );
        self::assertSame(
            [['level' => 'notice', 'message' => 'Incident résolu pour le moniteur {monitor} après {duration} s', 'context' => ['monitor' => $vMonitor->getId(), 'duration' => 600]]],
            $vLogger->withLevel('notice'),
        );
    }

    public function testIncidentCoveringTheWholePeriodMeansZeroAvailability(): void
    {
        $vNow = $this->aClock->now();
        $vMonitor = new Monitor('Hors service', MonitorType::Heartbeat, 3600, 600, $vNow->modify('-30 days'));
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->persist(new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-8 days')));
        $this->aEntityManager->flush();

        $vAvailability = static::getContainer()->get(AvailabilityCalculator::class)
            ->compute([$vMonitor], $vNow->modify('-7 days'), $vNow);

        self::assertSame(0.0, $vAvailability[$vMonitor->getId()]);
    }
}
