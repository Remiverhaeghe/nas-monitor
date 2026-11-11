<?php

namespace App\Tests\Chart;

use App\Chart\TrendChartFactory;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use PHPUnit\Framework\TestCase;

class TrendChartFactoryTest extends TestCase
{
    /**
     * @return list<Ping>
     */
    private function pings(): array
    {
        $vNow = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow);
        $vPings = [];
        foreach ([0, 1, 2] as $vIndex) {
            $vPing = new Ping($vMonitor, PingKind::Success, $vNow->modify(\sprintf('+%d hours', $vIndex)), 2000 + 500 * $vIndex, 1_048_576 * ($vIndex + 1));
            $vPing->setMetrics(['températures' => 40 + $vIndex, 'débit' => 10.5 * ($vIndex + 1)]);
            $vPings[] = $vPing;
        }

        return $vPings;
    }

    public function testDurationsAndSizesProduceACurve(): void
    {
        $vFactory = new TrendChartFactory('Europe/Paris');

        self::assertNotNull($vFactory->durations($this->pings()));
        self::assertNotNull($vFactory->sizes($this->pings()));
    }

    public function testMetricsProduceOneCurvePerName(): void
    {
        $vCharts = (new TrendChartFactory('Europe/Paris'))->metrics($this->pings());

        self::assertSame(['débit', 'températures'], array_keys($vCharts));
    }

    public function testSinglePointMakesNoCurve(): void
    {
        $vPings = \array_slice($this->pings(), 0, 1);
        $vFactory = new TrendChartFactory('Europe/Paris');

        self::assertNull($vFactory->durations($vPings));
        self::assertSame([], $vFactory->metrics($vPings));
    }
}
