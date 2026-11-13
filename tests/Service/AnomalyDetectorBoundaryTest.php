<?php

namespace App\Tests\Service;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\AnomalyDetector;
use PHPUnit\Framework\TestCase;

/**
 * Bornes et messages exacts du détecteur d'anomalies (seuils 50 % de taille, facteur 3 de durée).
 */
class AnomalyDetectorBoundaryTest extends TestCase
{
    private function ping(int $pDurationMs, int $pSizeBytes): Ping
    {
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());

        return new Ping($vMonitor, PingKind::Success, new \DateTimeImmutable(), $pDurationMs, $pSizeBytes);
    }

    /**
     * @param list<int> $pSizes
     * @param list<int> $pDurations
     *
     * @return list<Ping>
     */
    private function history(array $pSizes, array $pDurations): array
    {
        $rHistory = [];
        foreach ($pSizes as $vIndex => $vSize) {
            $rHistory[] = $this->ping($pDurations[$vIndex], $vSize);
        }

        return $rHistory;
    }

    /**
     * @return list<Ping>
     */
    private function steady(int $pCount, int $pSize, int $pDuration): array
    {
        return $this->history(array_fill(0, $pCount, $pSize), array_fill(0, $pCount, $pDuration));
    }

    /**
     * @param list<Ping> $pHistory
     */
    private function detect(Ping $pPing, array $pHistory): ?string
    {
        return (new AnomalyDetector(50, 3))->detect($pPing, $pHistory);
    }

    public function testFiveValuesAreEnoughForAVerdict(): void
    {
        self::assertNotNull($this->detect($this->ping(1000, 100), $this->steady(5, 1000, 1000)));
        self::assertNull($this->detect($this->ping(1000, 100), $this->steady(4, 1000, 1000)));
    }

    public function testSizeThresholdIsStrict(): void
    {
        $vHistory = $this->steady(5, 1000, 1000);

        self::assertNull($this->detect($this->ping(1000, 500), $vHistory));
        self::assertNull($this->detect($this->ping(1000, 505), $vHistory));
        self::assertNotNull($this->detect($this->ping(1000, 495), $vHistory));
    }

    public function testDurationThresholdIsStrictAndMessageIsExact(): void
    {
        $vHistory = $this->steady(5, 1000, 1000);

        self::assertNull($this->detect($this->ping(3000, 1000), $vHistory));
        self::assertSame('Durée de 3.0 s au lieu de 1.0 s habituellement', $this->detect($this->ping(3001, 1000), $vHistory));
    }

    public function testMedianOfAnEvenCountIsTheMeanOfTheMiddleValuesInAnyOrder(): void
    {
        $vSizes = [409601, 102400, 614400, 307200, 512000, 204800];
        $vHistory = $this->history($vSizes, array_fill(0, 6, 1000));

        self::assertSame('Taille de 166.0 Ko au lieu de 350.0 Ko habituellement', $this->detect($this->ping(1000, 170000), $vHistory));
        self::assertNull($this->detect($this->ping(1000, 180000), $vHistory));
    }

    public function testMedianOfAnOddCountIsTheMiddleValue(): void
    {
        $vSizes = [512000, 102400, 307200, 204800, 409600];
        $vHistory = $this->history($vSizes, array_fill(0, 5, 1000));

        self::assertSame('Taille de 146.5 Ko au lieu de 300.0 Ko habituellement', $this->detect($this->ping(1000, 150000), $vHistory));
        self::assertNull($this->detect($this->ping(1000, 160000), $vHistory));
    }

    public function testSizesSwitchToMegabytesAtExactlyOneMegabyte(): void
    {
        $vHistory = $this->steady(5, 3 * 1048576, 1000);

        self::assertSame('Taille de 1.0 Mo au lieu de 3.0 Mo habituellement', $this->detect($this->ping(1000, 1048576), $vHistory));
        self::assertSame('Taille de 99.6 Ko au lieu de 3.0 Mo habituellement', $this->detect($this->ping(1000, 102000), $vHistory));
    }

    public function testSizeJustUnderTheThresholdIsDetected(): void
    {
        self::assertNotNull($this->detect($this->ping(1000, 499), $this->steady(5, 1000, 1000)));
    }

    public function testDurationMessageUsesExactSeconds(): void
    {
        self::assertSame(
            'Durée de 200.0 s au lieu de 60.5 s habituellement',
            $this->detect($this->ping(200000, 1000), $this->steady(5, 1000, 60500)),
        );
    }

    public function testMegabyteRoundingIsExactAroundOnePointZeroFive(): void
    {
        $vHistory = $this->steady(5, 3 * 1048576, 1000);

        self::assertSame('Taille de 1.0 Mo au lieu de 3.0 Mo habituellement', $this->detect($this->ping(1000, 1101004), $vHistory));
        self::assertSame('Taille de 1.1 Mo au lieu de 3.0 Mo habituellement', $this->detect($this->ping(1000, 1101005), $vHistory));
    }

    public function testPingWithoutSizeIsNeverASizeAnomaly(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow);
        $vPing = new Ping($vMonitor, PingKind::Success, $vNow, 1000, null);

        self::assertNull($this->detect($vPing, $this->steady(5, 1000, 1000)));
    }

    public function testZeroMedianDurationGivesNoVerdict(): void
    {
        self::assertNull($this->detect($this->ping(1000, 1000), $this->steady(5, 1000, 0)));
    }

    public function testMissingValuesAreIgnoredWhenComputingTheMedian(): void
    {
        $vNow = new \DateTimeImmutable();
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow);
        $vHistory = [];
        for ($vIndex = 0; $vIndex < 5; ++$vIndex) {
            $vHistory[] = new Ping($vMonitor, PingKind::Success, $vNow, 1000, null);
        }
        foreach ($this->steady(5, 1000, 1000) as $vPing) {
            $vHistory[] = $vPing;
        }

        self::assertNotNull($this->detect($this->ping(1000, 400), $vHistory));
    }
}
