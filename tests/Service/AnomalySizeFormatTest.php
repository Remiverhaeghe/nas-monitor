<?php

namespace App\Tests\Service;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Service\AnomalyDetector;
use PHPUnit\Framework\TestCase;

class AnomalySizeFormatTest extends TestCase
{
    public function testSmallSizesAreShownInKilobytes(): void
    {
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $vHistory = [];
        for ($vIndex = 0; $vIndex < 10; ++$vIndex) {
            $vHistory[] = new Ping($vMonitor, PingKind::Success, new \DateTimeImmutable(), 60000, 100_000);
        }

        $vAnomaly = (new AnomalyDetector(50, 3))->detect(new Ping($vMonitor, PingKind::Success, new \DateTimeImmutable(), 60000, 10_000), $vHistory);

        self::assertNotNull($vAnomaly);
        self::assertStringContainsString('Ko', $vAnomaly);
    }
}
