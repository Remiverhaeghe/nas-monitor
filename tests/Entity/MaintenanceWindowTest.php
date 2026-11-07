<?php

namespace App\Tests\Entity;

use App\Entity\MaintenanceWindow;
use App\Enum\MaintenanceRecurrence;
use PHPUnit\Framework\TestCase;

class MaintenanceWindowTest extends TestCase
{
    private \DateTimeZone $aParis;

    protected function setUp(): void
    {
        $this->aParis = new \DateTimeZone('Europe/Paris');
    }

    public function testOneOffWindowEnds(): void
    {
        $vWindow = $this->window('2026-11-08 02:00', '2026-11-08 04:00', MaintenanceRecurrence::None);

        self::assertTrue($vWindow->isActive($this->paris('2026-11-08 03:00'), $this->aParis));
        self::assertNull($vWindow->findOccurrenceStart($this->paris('2026-11-08 04:00'), $this->aParis));
    }

    public function testWeeklyWindowRepeatsOnTheSameDay(): void
    {
        $vWindow = $this->window('2026-11-08 02:00', '2026-11-08 04:00', MaintenanceRecurrence::Weekly);

        self::assertTrue($vWindow->isActive($this->paris('2026-11-15 02:30'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-16 02:30'), $this->aParis));
        self::assertEquals($this->paris('2026-11-22 02:00'), $vWindow->findOccurrenceStart($this->paris('2026-11-15 05:00'), $this->aParis));
        self::assertSame('chaque dimanche de 02:00 à 04:00, à partir du 08/11/2026', $vWindow->describeSchedule($this->aParis));
    }

    public function testWeeklyWindowBoundaries(): void
    {
        $vWindow = $this->window('2026-11-08 02:00', '2026-11-08 04:00', MaintenanceRecurrence::Weekly);

        self::assertTrue($vWindow->isActive($this->paris('2026-11-15 02:00'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-15 04:00'), $this->aParis));
        self::assertEquals($this->paris('2026-11-22 02:00'), $vWindow->findOccurrenceStart($this->paris('2026-11-15 04:00'), $this->aParis));
        self::assertEquals($this->paris('2026-11-22 02:00'), $vWindow->findOccurrenceStart($this->paris('2026-11-18 03:00'), $this->aParis));
    }

    public function testDailyWindowKeepsLocalTimeAfterClockChange(): void
    {
        $vWindow = $this->window('2026-10-20 03:00', '2026-10-20 03:30', MaintenanceRecurrence::Daily);

        self::assertTrue($vWindow->isActive($this->paris('2026-11-07 03:10'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-07 02:10'), $this->aParis));
    }

    public function testDailyWindowOverMidnightIsActiveAfterMidnight(): void
    {
        $vWindow = $this->window('2026-11-01 23:00', '2026-11-02 02:00', MaintenanceRecurrence::Daily);

        self::assertTrue($vWindow->isActive($this->paris('2026-11-07 01:00'), $this->aParis));
        self::assertTrue($vWindow->isActive($this->paris('2026-11-07 23:30'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-07 02:00'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-07 12:00'), $this->aParis));
        self::assertFalse($vWindow->isActive($this->paris('2026-11-01 01:00'), $this->aParis));
    }

    private function window(string $pStart, string $pEnd, MaintenanceRecurrence $pRecurrence): MaintenanceWindow
    {
        $rWindow = new MaintenanceWindow(null, $this->paris($pStart), $this->paris($pEnd), 'Mise à jour', $pRecurrence);

        return $rWindow;
    }

    private function paris(string $pDate): \DateTimeImmutable
    {
        $rDate = (new \DateTimeImmutable($pDate, $this->aParis))->setTimezone(new \DateTimeZone('UTC'));

        return $rDate;
    }
}
