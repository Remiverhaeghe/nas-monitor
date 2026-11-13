<?php

namespace App\Tests\Report;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Report\WeeklyReportSender;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use App\Service\AvailabilityCalculator;
use App\Tests\Support\RecordingLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

class WeeklyReportBodyExactTest extends KernelTestCase
{
    private EntityManagerInterface $aEntityManager;
    private RecordingLogger $aLogger;
    private MockClock $aClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }
        $this->aLogger = new RecordingLogger();
        $this->aClock = new MockClock('2026-11-11 12:00:00');
    }

    private function sender(?MailerInterface $pMailer = null): WeeklyReportSender
    {
        $vContainer = static::getContainer();

        return new WeeklyReportSender(
            $vContainer->get(MonitorRepository::class),
            $vContainer->get(IncidentRepository::class),
            $vContainer->get(PingRepository::class),
            $vContainer->get(AvailabilityCalculator::class),
            $pMailer ?? $this->createStub(MailerInterface::class),
            $this->aClock,
            $this->aLogger,
            'nas-monitor@localhost',
            'admin@localhost',
            'Europe/Paris',
        );
    }

    public function testBodyListsAvailabilityIncidentsAndAnomaliesWithExactPlurals(): void
    {
        $vTo = $this->aClock->now();
        $vFrom = $vTo->modify('-7 days');
        $vBusy = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-10-01 00:00:00'));
        $vCalm = new Monitor('Calme', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-10-01 00:00:00'));
        $this->aEntityManager->persist($vBusy);
        $this->aEntityManager->persist($vCalm);
        $vIncident = new Incident($vBusy, IncidentCause::Missed, $vFrom);
        $vIncident->resolve($vFrom->modify('+1 hour'));
        $this->aEntityManager->persist($vIncident);
        foreach ([1, 2] as $vDays) {
            $vPing = new Ping($vBusy, PingKind::Success, $vTo->modify(\sprintf('-%d days', $vDays)), 1000, 1000);
            $vPing->markAnomaly('taille anormale');
            $this->aEntityManager->persist($vPing);
        }
        $this->aEntityManager->flush();

        $vBody = $this->sender()->buildBody($vFrom, $vTo);

        $vLines = explode("\n", $vBody);
        self::assertStringStartsWith('Disponibilité du ', $vLines[0]);
        self::assertStringEndsWith(', heure de Paris.', $vLines[0]);
        self::assertSame('', $vLines[1]);
        $vBusyLabel = mb_strtolower($vBusy->getStatus()->label());
        self::assertContains('- Sauvegarde : 99,4 %, 1 incident, 2 anomalies, actuellement '.$vBusyLabel, $vLines);
        self::assertContains('- Calme : 100,0 %, 0 incident, 0 anomalie, actuellement '.mb_strtolower($vCalm->getStatus()->label()), $vLines);
        self::assertStringNotContainsString('Sans groupe', $vBody);
        self::assertStringEndsWith("\n", $vBody);
    }

    public function testSendingLogsTheReport(): void
    {
        $vMailer = $this->createMock(MailerInterface::class);
        $vMailer->expects(self::once())->method('send');

        $this->sender($vMailer)->send();

        self::assertSame(
            [['level' => 'info', 'message' => 'Rapport hebdomadaire envoyé', 'context' => []]],
            $this->aLogger->records,
        );
    }

    public function testSeveralIncidentsOfTheSameMonitorAreCounted(): void
    {
        $vTo = $this->aClock->now();
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-10-01 00:00:00'));
        $this->aEntityManager->persist($vMonitor);
        foreach (['-2 days', '-1 day', '-3 hours'] as $vStart) {
            $vIncident = new Incident($vMonitor, IncidentCause::Missed, $vTo->modify($vStart));
            $vIncident->resolve($vTo->modify($vStart.' +10 minutes'));
            $this->aEntityManager->persist($vIncident);
        }
        $this->aEntityManager->flush();

        self::assertStringContainsString(', 3 incidents, ', $this->sender()->buildBody($vTo->modify('-7 days'), $vTo));
    }
}
