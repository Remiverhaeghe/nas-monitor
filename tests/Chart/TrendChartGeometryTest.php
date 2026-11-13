<?php

namespace App\Tests\Chart;

use App\Chart\TrendChart;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs exactes des coordonnées : un changement de formule doit casser un test.
 */
class TrendChartGeometryTest extends TestCase
{
    /**
     * @param list<float> $pValues
     *
     * @return list<array{date: \DateTimeImmutable, value: float}>
     */
    private function series(array $pValues): array
    {
        $vStart = new \DateTimeImmutable('2026-11-11 10:00:00', new \DateTimeZone('UTC'));
        $rSeries = [];
        foreach ($pValues as $vIndex => $vValue) {
            $rSeries[] = ['date' => $vStart->modify(\sprintf('+%d hours', $vIndex)), 'value' => $vValue];
        }

        return $rSeries;
    }

    /**
     * @param list<float> $pValues
     * @param list<float> $pExpectedY
     */
    #[DataProvider('provideCurves')]
    public function testCoordinates(array $pValues, array $pExpectedY, float $pMin, float $pMax): void
    {
        $vChart = TrendChart::build($this->series($pValues), 'u', '%.1f', new \DateTimeZone('Europe/Paris'));

        self::assertNotNull($vChart);
        self::assertSame($pExpectedY, array_column($vChart->points, 'y'));
        self::assertSame($pMin, $vChart->minValue);
        self::assertSame($pMax, $vChart->maxValue);
    }

    /**
     * @return iterable<string, array{list<float>, list<float>, float, float}>
     */
    public static function provideCurves(): iterable
    {
        yield 'positives depuis zéro' => [[0.0, 10.0, 20.0], [128.0, 70.0, 12.0], 0.0, 20.0];
        yield 'le minimum reste à zéro' => [[5.0, 10.0], [70.0, 12.0], 0.0, 10.0];
        yield 'valeurs négatives' => [[-10.0, 10.0], [128.0, 12.0], -10.0, 10.0];
        yield 'toutes à zéro' => [[0.0, 0.0], [128.0, 128.0], 0.0, 0.0];
    }

    public function testAbscissasPolylineLabelsAndDates(): void
    {
        $vChart = TrendChart::build($this->series([0.0, 10.0, 20.0]), 'MB', '%.1f', new \DateTimeZone('Europe/Paris'));

        self::assertNotNull($vChart);
        self::assertSame([8.0, 300.0, 592.0], array_column($vChart->points, 'x'));
        self::assertSame('8,128 300,70 592,12', $vChart->polyline);
        self::assertSame('11/11 11:00 : 0.0 MB', $vChart->points[0]['label']);
        self::assertSame('MB', $vChart->unit);
        self::assertSame('2026-11-11 10:00', $vChart->firstDate->format('Y-m-d H:i'));
        self::assertSame('2026-11-11 12:00', $vChart->lastDate->format('Y-m-d H:i'));
    }

    public function testFractionalCoordinatesAreRoundedToOneDecimal(): void
    {
        $vChart = TrendChart::build($this->series([0.0, 1.0, 3.0, 7.0]), 'u', '%.1f', new \DateTimeZone('UTC'));

        self::assertNotNull($vChart);
        self::assertSame([8.0, 202.7, 397.3, 592.0], array_column($vChart->points, 'x'));
        self::assertSame([128.0, 111.4, 78.3, 12.0], array_column($vChart->points, 'y'));
    }
}
