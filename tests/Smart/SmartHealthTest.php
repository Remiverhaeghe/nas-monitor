<?php

namespace App\Tests\Smart;

use App\Smart\SmartDiskReport;
use App\Smart\SmartHealth;
use PHPUnit\Framework\TestCase;

class SmartHealthTest extends TestCase
{
    public function testMetricsKeepTheWorstDiskAndCountFailures(): void
    {
        $vMetrics = SmartHealth::metrics([
            'sda' => new SmartDiskReport(false, 41, 8, 0, 0),
            'sdb' => new SmartDiskReport(false, 47, 2, 3),
            'sdc' => new SmartDiskReport(true, 39),
            'nvme0n1' => new SmartDiskReport(null, 44, null, null, null, 1, 7),
        ]);

        self::assertSame([
            'disk_temperature_max_c' => 47.0,
            'smart_failed_disks' => 2.0,
            'smart_reallocated_sectors' => 8.0,
            'smart_pending_sectors' => 3.0,
            'smart_uncorrectable_sectors' => 0.0,
            'smart_media_errors' => 1.0,
            'smart_wear_percent' => 7.0,
        ], $vMetrics);
        self::assertSame([], SmartHealth::metrics(['sda' => new SmartDiskReport()]));
    }

    public function testHealthyDisksRaiseNothing(): void
    {
        self::assertSame([], SmartHealth::findExceeded([
            'sda' => new SmartDiskReport(true, 50, 0, 0, 0),
            'nvme0n1' => new SmartDiskReport(true, 44, null, null, null, 0, 90),
            'sdb' => new SmartDiskReport(),
        ], []));
        self::assertSame([], SmartHealth::findExceeded([], []));
    }

    public function testDefaultLimitsFlagEachDiskBeforeFailure(): void
    {
        $vExceeded = SmartHealth::findExceeded([
            'sda' => new SmartDiskReport(true, 52, 24, 3, 1),
            'sdb' => new SmartDiskReport(false, 41),
            'nvme0n1' => new SmartDiskReport(true, 44, null, null, null, 5, 96),
        ], ['disk_used_percent' => 85.0]);

        self::assertSame([
            'test SMART en échec : sdb',
            'sda : disk_temperature_max_c à 52 (maximum 50)',
            'sda : smart_reallocated_sectors à 24 (maximum 0)',
            'sda : smart_pending_sectors à 3 (maximum 0)',
            'sda : smart_uncorrectable_sectors à 1 (maximum 0)',
            'nvme0n1 : smart_media_errors à 5 (maximum 0)',
            'nvme0n1 : smart_wear_percent à 96 (maximum 90)',
        ], $vExceeded);
    }

    public function testMonitorLimitsReplaceTheDefaults(): void
    {
        $vReports = [
            'sda' => new SmartDiskReport(false, 52, 24),
            'sdb' => new SmartDiskReport(false, 40),
        ];

        self::assertSame(
            ['test SMART en échec : sda, sdb', 'sda : smart_reallocated_sectors à 24 (maximum 20)'],
            SmartHealth::findExceeded($vReports, ['disk_temperature_max_c' => 55.0, 'smart_reallocated_sectors' => 20.0, 'smart_failed_disks' => 1.0]),
        );
        self::assertSame([], SmartHealth::findExceeded($vReports, ['disk_temperature_max_c' => 55.0, 'smart_reallocated_sectors' => 30.0, 'smart_failed_disks' => 2.0]));
    }

    public function testOnlySmartMetricsAreRecognised(): void
    {
        self::assertTrue(SmartHealth::isSmartMetric('smart_failed_disks'));
        self::assertTrue(SmartHealth::isSmartMetric('disk_temperature_max_c'));
        self::assertFalse(SmartHealth::isSmartMetric('temperature_c'));
    }
}
