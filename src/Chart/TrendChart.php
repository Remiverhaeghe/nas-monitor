<?php

namespace App\Chart;

/**
 * Courbe d'une seule série, coordonnées déjà calculées pour un SVG de WIDTH x HEIGHT.
 */
final class TrendChart
{
    public const WIDTH = 600;
    public const HEIGHT = 140;
    private const PADDING_TOP = 12;
    private const PADDING_BOTTOM = 12;
    private const PADDING_X = 8;

    /**
     * @param list<array{x: float, y: float, label: string}> $points
     */
    private function __construct(
        public readonly array $points,
        public readonly string $polyline,
        public readonly float $minValue,
        public readonly float $maxValue,
        public readonly string $unit,
        public readonly \DateTimeImmutable $firstDate,
        public readonly \DateTimeImmutable $lastDate,
    ) {
    }

    /**
     * Retourne null s'il y a moins de deux valeurs : une courbe n'aurait pas de sens.
     *
     * @param list<array{date: \DateTimeImmutable, value: float}> $pSeries
     */
    public static function build(array $pSeries, string $pUnit, string $pValueFormat, \DateTimeZone $pTimezone): ?self
    {
        if (\count($pSeries) < 2) {
            return null;
        }

        $vValues = array_column($pSeries, 'value');
        $vMin = min(0.0, min($vValues));
        $vMax = max($vValues);
        $vRange = $vMax - $vMin > 0 ? $vMax - $vMin : 1.0;
        $vStepX = (self::WIDTH - 2 * self::PADDING_X) / (\count($pSeries) - 1);
        $vPlotHeight = self::HEIGHT - self::PADDING_TOP - self::PADDING_BOTTOM;

        $vPoints = [];
        foreach ($pSeries as $vIndex => $vEntry) {
            $vPoints[] = [
                'x' => round(self::PADDING_X + $vIndex * $vStepX, 1),
                'y' => round(self::PADDING_TOP + $vPlotHeight * (1 - ($vEntry['value'] - $vMin) / $vRange), 1),
                'label' => \sprintf(
                    '%s : '.$pValueFormat.' %s',
                    $vEntry['date']->setTimezone($pTimezone)->format('d/m H:i'),
                    $vEntry['value'],
                    $pUnit,
                ),
            ];
        }

        $rChart = new self(
            $vPoints,
            implode(' ', array_map(static fn (array $pPoint): string => $pPoint['x'].','.$pPoint['y'], $vPoints)),
            $vMin,
            $vMax,
            $pUnit,
            $pSeries[0]['date'],
            $pSeries[\count($pSeries) - 1]['date'],
        );

        return $rChart;
    }
}
