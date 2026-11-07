<?php

namespace App\Tests\Chart;

use App\Chart\TrendChart;
use PHPUnit\Framework\TestCase;

class TrendChartTest extends TestCase
{
    public function testSingleValueGivesNoChart(): void
    {
        self::assertNull(TrendChart::build([['date' => new \DateTimeImmutable(), 'value' => 3.0]], 's', '%.1f', new \DateTimeZone('UTC')));
    }

    public function testPointsSpanTheWidthAndHigherValueIsHigher(): void
    {
        $vSeries = [
            ['date' => new \DateTimeImmutable('2026-11-07 10:00:00'), 'value' => 2.0],
            ['date' => new \DateTimeImmutable('2026-11-07 11:00:00'), 'value' => 8.0],
            ['date' => new \DateTimeImmutable('2026-11-07 12:00:00'), 'value' => 4.0],
        ];

        $vChart = TrendChart::build($vSeries, 's', '%.1f', new \DateTimeZone('Europe/Paris'));

        self::assertNotNull($vChart);
        self::assertCount(3, $vChart->points);
        self::assertLessThan($vChart->points[0]['y'], $vChart->points[1]['y']);
        self::assertSame(8.0, $vChart->maxValue);
        self::assertSame('07/11 12:00 : 8.0 s', $vChart->points[1]['label']);
        self::assertSame(3, substr_count($vChart->polyline, ','));
    }
}
