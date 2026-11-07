<?php

namespace App\Tests\Service;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Report\WeeklyReportSender;
use App\Repository\MonitorRepository;
use App\Service\AvailabilityCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class ReportingTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const NOW = '2026-11-09 06:00:00';

    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime(self::NOW);
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
    }

    public function testAvailabilityCountsIncidentsInsideThePeriod(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);
        $vMonitor = $this->createMonitor('Sauvegarde restic', null, $vNow->modify('-30 days'));
        $vOld = new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-8 days'));
        $vOld->resolve($vNow->modify('-7 days +1 hour'));
        $vResolved = new Incident($vMonitor, IncidentCause::Failed, $vNow->modify('-3 days'));
        $vResolved->resolve($vNow->modify('-3 days +5 hours'));
        $vOpen = new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-1 hour'));
        $vRecent = $this->createMonitor('Synchronisation', null, $vNow->modify('-1 day'));
        foreach ([$vOld, $vResolved, $vOpen] as $vIncident) {
            $this->aEntityManager->persist($vIncident);
        }
        $this->aEntityManager->flush();

        $vAvailability = static::getContainer()->get(AvailabilityCalculator::class)
            ->compute([$vMonitor, $vRecent], $vNow->modify('-7 days'), $vNow);

        self::assertEqualsWithDelta(100 * (1 - 7 / 168), $vAvailability[$vMonitor->getId()], 0.001);
        self::assertSame(100.0, $vAvailability[$vRecent->getId()]);
    }

    public function testMonitorsAreGroupedWithUngroupedLast(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);
        $this->createMonitor('Site web', 'Services', $vNow);
        $this->createMonitor('Divers', null, $vNow);
        $this->createMonitor('Photos', 'Sauvegardes', $vNow);
        $this->createMonitor('Documents', 'Sauvegardes', $vNow);

        $vGroups = static::getContainer()->get(MonitorRepository::class)->findAllGrouped();

        self::assertSame(['Sauvegardes', 'Services', null], array_column($vGroups, 'name'));
        self::assertSame(['Documents', 'Photos'], array_map(static fn (Monitor $pMonitor): string => $pMonitor->getName(), $vGroups[0]['monitors']));
    }

    public function testWeeklyReportIsSentByEmail(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);
        $vMonitor = $this->createMonitor('Sauvegarde restic', 'Sauvegardes', $vNow->modify('-30 days'));
        $vPing = new Ping($vMonitor, PingKind::Success, $vNow->modify('-2 days'));
        $vPing->markAnomaly('Taille de 10 Mo au lieu de 900 Mo habituellement');
        $this->aEntityManager->persist($vPing);
        $vIncident = new Incident($vMonitor, IncidentCause::Missed, $vNow->modify('-2 days'));
        $vIncident->resolve($vNow->modify('-2 days +168 minutes'));
        $this->aEntityManager->persist($vIncident);
        $this->aEntityManager->flush();

        static::getContainer()->get(WeeklyReportSender::class)->send();

        self::assertQueuedEmailCount(1);
        $vEmail = self::getMailerMessage(0);
        self::assertEmailSubjectContains($vEmail, 'Rapport hebdomadaire');
        self::assertEmailTextBodyContains($vEmail, 'Disponibilité du 02/11/2026 07:00 au 09/11/2026 07:00');
        self::assertEmailTextBodyContains($vEmail, "Sauvegardes\n- Sauvegarde restic : 98,3 %, 1 incident, 1 anomalie, actuellement nouveau");
    }

    private function createMonitor(string $pName, ?string $pGroupName, \DateTimeImmutable $pCreatedAt): Monitor
    {
        $rMonitor = new Monitor($pName, MonitorType::Heartbeat, 3600, 600, $pCreatedAt);
        $rMonitor->setGroupName($pGroupName);
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }
}
