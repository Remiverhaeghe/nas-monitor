<?php

namespace App\Chart;

use App\Entity\Ping;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TrendChartFactory
{
    private \DateTimeZone $aTimezone;

    public function __construct(
        #[Autowire(param: 'app.display_timezone')]
        string $pDisplayTimezone,
    ) {
        $this->aTimezone = new \DateTimeZone($pDisplayTimezone);
    }

    /**
     * @param list<Ping> $pPings
     */
    public function durations(array $pPings): ?TrendChart
    {
        $vSeries = [];
        foreach ($pPings as $vPing) {
            if (null !== $vPing->getDurationMs()) {
                $vSeries[] = ['date' => $vPing->getReceivedAt(), 'value' => $vPing->getDurationMs() / 1000];
            }
        }

        $rChart = TrendChart::build($vSeries, 's', '%.1f', $this->aTimezone);

        return $rChart;
    }

    /**
     * @param list<Ping> $pPings
     */
    public function sizes(array $pPings): ?TrendChart
    {
        $vSeries = [];
        foreach ($pPings as $vPing) {
            if (null !== $vPing->getSizeBytes()) {
                $vSeries[] = ['date' => $vPing->getReceivedAt(), 'value' => $vPing->getSizeBytes() / 1048576];
            }
        }

        $rChart = TrendChart::build($vSeries, 'Mo', '%.1f', $this->aTimezone);

        return $rChart;
    }
}
