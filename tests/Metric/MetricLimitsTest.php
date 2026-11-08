<?php

namespace App\Tests\Metric;

use App\Metric\MetricLimits;
use PHPUnit\Framework\TestCase;

class MetricLimitsTest extends TestCase
{
    public function testLimitsAreParsedAndFormatted(): void
    {
        $vLimits = MetricLimits::parse(" disk_used_percent = 90,temperature_c=62.5 ;\nsmart_failed_disks=0 ");

        self::assertSame(['disk_used_percent' => 90.0, 'temperature_c' => 62.5, 'smart_failed_disks' => 0.0], $vLimits);
        self::assertSame('disk_used_percent=90, temperature_c=62.5, smart_failed_disks=0', MetricLimits::format($vLimits));
        self::assertSame([], MetricLimits::parse(''));
    }

    public function testInvalidLimitIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('« Disque=90 »');

        MetricLimits::parse('Disque=90');
    }

    public function testOnlyValuesAboveTheirLimitAreReported(): void
    {
        $vExceeded = MetricLimits::findExceeded(
            ['disk_used_percent' => 90.0, 'temperature_c' => 71.25, 'load_1m' => 9.0],
            ['disk_used_percent' => 90.0, 'temperature_c' => 70.0, 'smart_failed_disks' => 0.0],
        );

        self::assertSame(['temperature_c à 71.25 (maximum 70)'], $vExceeded);
    }
}
