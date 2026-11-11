<?php

namespace App\Tests\Entity;

use App\Entity\ApiToken;
use App\Entity\AuditLog;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\MaintenanceRecurrence;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use PHPUnit\Framework\TestCase;

class AccessorsTest extends TestCase
{
    private const NOW = '2026-11-11 12:00:00';

    public function testNewEntitiesHaveNoIdentifierYet(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);
        $vUser = new User('a@example.com');
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow);

        self::assertNull((new ApiToken($vUser, 'tel', 'hash', $vNow))->getId());
        self::assertNull((new AuditLog($vNow, 'a', AuditAction::Login, 'détail', null))->getId());
        self::assertNull((new Ping($vMonitor, PingKind::Success, $vNow))->getId());
    }

    public function testApiTokenKeepsItsCreationDate(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);

        self::assertSame($vNow, (new ApiToken(new User('a@example.com'), 'tel', 'hash', $vNow))->getCreatedAt());
    }

    public function testPingKeepsItsMonitor(): void
    {
        $vNow = new \DateTimeImmutable(self::NOW);
        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, $vNow);

        self::assertSame($vMonitor, (new Ping($vMonitor, PingKind::Fail, $vNow))->getMonitor());
    }

    public function testMaintenanceWindowExposesEndAndRecurrence(): void
    {
        $vStart = new \DateTimeImmutable(self::NOW);
        $vEnd = $vStart->modify('+1 hour');
        $vWindow = new MaintenanceWindow(null, $vStart, $vEnd, 'Mise à jour', MaintenanceRecurrence::Weekly);

        self::assertSame($vEnd, $vWindow->getEndsAt());
        self::assertSame(MaintenanceRecurrence::Weekly, $vWindow->getRecurrence());
    }

    public function testUserWithoutEmailCannotBeIdentified(): void
    {
        $this->expectException(\LogicException::class);
        (new User(''))->getUserIdentifier();
    }

    public function testNewUserHasNoBackupCodeAndNoTotpConfiguration(): void
    {
        $vUser = new User('a@example.com');

        self::assertSame(0, $vUser->countBackupCodes());
        self::assertNull($vUser->getTotpAuthenticationConfiguration());
    }

    public function testBackupCodesAreCounted(): void
    {
        $vUser = new User('a@example.com');
        $vUser->replaceBackupCodes(['aaaa-bbbb-cccc-dddd', 'eeee-ffff-0000-1111']);

        self::assertSame(2, $vUser->countBackupCodes());
    }
}
