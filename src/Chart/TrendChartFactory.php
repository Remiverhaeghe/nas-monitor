<?php

namespace App\Chart;

use App\Entity\Ping;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TrendChartFactory
{
    private const MAX_METRIC_CHARTS = 6;

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
     * Une courbe par métrique reçue, au plus MAX_METRIC_CHARTS, par ordre alphabétique.
     *
     * @param list<Ping> $pPings
     *
     * @return array<string, TrendChart>
     */
    public function metrics(array $pPings): array
    {
        $vSeries = [];
        foreach ($pPings as $vPing) {
            foreach ($vPing->getMetrics() ?? [] as $vName => $vValue) {
                $vSeries[$vName][] = ['date' => $vPing->getReceivedAt(), 'value' => $vValue];
            }
        }
        ksort($vSeries);

        $rCharts = [];
        foreach (\array_slice($vSeries, 0, self::MAX_METRIC_CHARTS, true) as $vName => $vPoints) {
            $vChart = TrendChart::build($vPoints, '', '%.1f', $this->aTimezone);
            if (null !== $vChart) {
                $rCharts[(string) $vName] = $vChart;
            }
        }

        return $rCharts;
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
