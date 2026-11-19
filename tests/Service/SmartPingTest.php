<?php

namespace App\Tests\Service;

use App\Dto\PingPayload;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\PingRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Relevés du NAS accompagnés des sorties de smartctl : métriques tirées des disques et alerte avant panne.
 */
class SmartPingTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-19 10:00:00');
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
    }

    public function testHealthyDisksGiveMetricsWithoutAnomaly(): void
    {
        $vMonitor = $this->createMonitor(['disk_used_percent' => 90.0]);

        $vPing = $this->record($vMonitor, ['disk_used_percent' => 70, 'smart_failed_disks' => 3], ['sda' => 'sata-sain.txt', 'nvme0n1' => 'nvme.txt']);

        self::assertSame([
            'disk_used_percent' => 70.0,
            'disk_temperature_max_c' => 44.0,
            'smart_failed_disks' => 0.0,
            'smart_reallocated_sectors' => 0.0,
            'smart_pending_sectors' => 0.0,
            'smart_uncorrectable_sectors' => 0.0,
            'smart_media_errors' => 0.0,
            'smart_wear_percent' => 7.0,
        ], $vPing->getMetrics());
        self::assertNull($vPing->getAnomaly());
        self::assertQueuedEmailCount(0);
    }

    public function testWornDiskRaisesOneAnomalyAlertNamingTheDisk(): void
    {
        $vMonitor = $this->createMonitor(['disk_used_percent' => 90.0, 'smart_reallocated_sectors' => 10.0, 'disk_temperature_max_c' => 55.0]);

        $vPing = $this->record($vMonitor, ['disk_used_percent' => 95], ['sda' => 'sata-sain.txt', 'sdb' => 'sata-use.txt']);

        self::assertSame(
            'disk_used_percent à 95 (maximum 90) ; sdb : smart_reallocated_sectors à 24 (maximum 10) ; sdb : smart_pending_sectors à 3 (maximum 0) ; sdb : smart_uncorrectable_sectors à 1 (maximum 0)',
            $vPing->getAnomaly(),
        );
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(0), 'Anomalie : Relevé du NAS');

        $this->record($vMonitor, null, ['sdb' => 'sata-use.txt']);
        self::assertQueuedEmailCount(1);
    }

    public function testDisksInStandbyAddNoMetric(): void
    {
        $vMonitor = $this->createMonitor([]);

        $vPing = $this->record($vMonitor, null, ['sda' => 'veille.txt']);

        self::assertNull($vPing->getMetrics());
        self::assertNull($vPing->getAnomaly());
    }

    public function testFailedDiskCountFromAnOlderScriptIsStillChecked(): void
    {
        $vMonitor = $this->createMonitor(['smart_failed_disks' => 0.0]);

        $vPing = $this->record($vMonitor, ['smart_failed_disks' => 1], []);

        self::assertSame('smart_failed_disks à 1 (maximum 0)', $vPing->getAnomaly());
    }

    /**
     * @param array<string, float> $pLimits
     */
    private function createMonitor(array $pLimits): Monitor
    {
        $rMonitor = new Monitor('Relevé du NAS', MonitorType::Heartbeat, 600, 300, new \DateTimeImmutable('2026-11-19 09:00:00'));
        $rMonitor->setMetricLimits($pLimits);
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }

    /**
     * @param array<string, int|float>|null $pMetrics
     * @param array<string, string>         $pSmartFiles fichier de sortie smartctl fictive par disque
     */
    private function record(Monitor $pMonitor, ?array $pMetrics, array $pSmartFiles): Ping
    {
        $vPayload = new PingPayload();
        $vPayload->metrics = $pMetrics;
        $vPayload->smart = array_map(static fn (string $pFile): string => (string) file_get_contents(__DIR__.'/../Smart/fixtures/'.$pFile), $pSmartFiles);
        $rPing = static::getContainer()->get(PingRecorder::class)->record($pMonitor, PingKind::Success, $vPayload, null);

        return $rPing;
    }
}
