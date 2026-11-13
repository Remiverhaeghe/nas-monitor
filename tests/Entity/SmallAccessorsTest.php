<?php

namespace App\Tests\Entity;

use App\Entity\AuditLog;
use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\AuditAction;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use PHPUnit\Framework\TestCase;

class SmallAccessorsTest extends TestCase
{
    public function testAuditLogExposesItsIpAddress(): void
    {
        $vLog = new AuditLog(new \DateTimeImmutable(), 'a', AuditAction::Login, 'détail', '10.0.0.1');

        self::assertSame('10.0.0.1', $vLog->getIpAddress());
    }

    public function testAnomalyIsCutAtTwoHundredFiftyFiveCharacters(): void
    {
        $vNow = new \DateTimeImmutable();
        $vPing = new Ping(new Monitor('S', MonitorType::Heartbeat, 3600, 600, $vNow), PingKind::Success, $vNow);

        $vPing->markAnomaly(str_repeat('é', 300));

        self::assertSame(255, mb_strlen((string) $vPing->getAnomaly()));
        self::assertSame(str_repeat('é', 255), $vPing->getAnomaly());
    }

    public function testIncidentDurationStopsAtResolution(): void
    {
        $vStart = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vMonitor = new Monitor('S', MonitorType::Heartbeat, 3600, 600, $vStart);
        $vIncident = new Incident($vMonitor, IncidentCause::Missed, $vStart);

        self::assertSame(3600, $vIncident->getDurationSeconds($vStart->modify('+1 hour')));

        $vIncident->resolve($vStart->modify('+10 minutes'));

        self::assertSame(600, $vIncident->getDurationSeconds($vStart->modify('+5 hours')));
    }

    public function testPublicAccessorsStayPublic(): void
    {
        $vNow = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vPing = new Ping(new Monitor('S', MonitorType::Heartbeat, 3600, 600, $vNow), PingKind::Success, $vNow, null, null, null, '10.0.0.2');
        $vWindow = new MaintenanceWindow(null, $vNow, $vNow->modify('+3 hours'), 'Mise à jour');

        self::assertSame('10.0.0.2', $vPing->getSourceIp());
        self::assertSame(10800, $vWindow->getDurationSeconds());
    }
}
