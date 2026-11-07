<?php

namespace App\Tests\Service;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\AnomalyDetector;
use PHPUnit\Framework\TestCase;

class AnomalyDetectorTest extends TestCase
{
    private Monitor $aMonitor;

    protected function setUp(): void
    {
        $this->aMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
    }

    public function testNormalPingHasNoAnomaly(): void
    {
        self::assertNull($this->detect(60000, 1000000000));
    }

    public function testSizeDropIsDetected(): void
    {
        $vAnomaly = $this->detect(60000, 400000000);

        self::assertNotNull($vAnomaly);
        self::assertStringContainsString('Taille de 381.5 Mo au lieu de 953.7 Mo', $vAnomaly);
    }

    public function testDurationSpikeIsDetected(): void
    {
        $vAnomaly = $this->detect(200000, 1000000000);

        self::assertNotNull($vAnomaly);
        self::assertSame('Durée de 200.0 s au lieu de 60.2 s habituellement', $vAnomaly);
    }

    public function testNotEnoughHistoryGivesNoVerdict(): void
    {
        $vDetector = new AnomalyDetector(50, 3);

        self::assertNull($vDetector->detect($this->ping(60000, 1), $this->history(4)));
    }

    private function detect(int $pDurationMs, int $pSizeBytes): ?string
    {
        $rAnomaly = (new AnomalyDetector(50, 3))->detect($this->ping($pDurationMs, $pSizeBytes), $this->history(10));

        return $rAnomaly;
    }

    /**
     * @return list<Ping>
     */
    private function history(int $pCount): array
    {
        $rHistory = [];
        for ($vIndex = 0; $vIndex < $pCount; ++$vIndex) {
            $rHistory[] = $this->ping(58000 + $vIndex * 500, 1000000000 + $vIndex * 1000);
        }

        return $rHistory;
    }

    private function ping(int $pDurationMs, int $pSizeBytes): Ping
    {
        $rPing = new Ping($this->aMonitor, PingKind::Success, new \DateTimeImmutable(), $pDurationMs, $pSizeBytes);

        return $rPing;
    }
}
