<?php

namespace App\Tests\Entity;

use App\Entity\Monitor;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use PHPUnit\Framework\TestCase;

class MonitorTest extends TestCase
{
    private const INTERVAL = 3600;
    private const GRACE = 600;

    private \DateTimeImmutable $aNow;

    protected function setUp(): void
    {
        $this->aNow = new \DateTimeImmutable('2026-11-06 12:00:00');
    }

    public function testNewMonitorHasStatusNew(): void
    {
        $vMonitor = $this->createMonitor();

        self::assertSame(MonitorStatus::New, $vMonitor->getStatus());
        self::assertSame(MonitorStatus::New, $vMonitor->evaluateStatus($this->aNow->modify('+1 day')));
    }

    public function testSuccessPingSetsStatusUp(): void
    {
        $vMonitor = $this->createMonitor();

        $vChanged = $vMonitor->recordPing(PingKind::Success, $this->aNow);

        self::assertTrue($vChanged);
        self::assertSame(MonitorStatus::Up, $vMonitor->getStatus());
        self::assertEquals($this->aNow, $vMonitor->getLastPingAt());
    }

    public function testStartPingDoesNotChangeStatus(): void
    {
        $vMonitor = $this->createMonitor();

        $vChanged = $vMonitor->recordPing(PingKind::Start, $this->aNow);

        self::assertFalse($vChanged);
        self::assertSame(MonitorStatus::New, $vMonitor->getStatus());
        self::assertNull($vMonitor->getLastPingAt());
    }

    public function testFailPingSetsStatusDown(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Success, $this->aNow);

        $vMonitor->recordPing(PingKind::Fail, $this->aNow->modify('+10 minutes'));

        self::assertSame(MonitorStatus::Down, $vMonitor->getStatus());
    }

    public function testStatusFollowsIntervalAndGrace(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Success, $this->aNow);

        $vEndOfInterval = $this->aNow->modify('+'.self::INTERVAL.' seconds');
        $vEndOfGrace = $vEndOfInterval->modify('+'.self::GRACE.' seconds');

        self::assertSame(MonitorStatus::Up, $vMonitor->evaluateStatus($vEndOfInterval));
        self::assertSame(MonitorStatus::Late, $vMonitor->evaluateStatus($vEndOfInterval->modify('+1 second')));
        self::assertSame(MonitorStatus::Late, $vMonitor->evaluateStatus($vEndOfGrace));
        self::assertSame(MonitorStatus::Down, $vMonitor->evaluateStatus($vEndOfGrace->modify('+1 second')));
    }

    public function testRefreshStatusReportsChangeOnlyOnce(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Success, $this->aNow);
        $vLater = $this->aNow->modify('+1 day');

        self::assertTrue($vMonitor->refreshStatus($vLater));
        self::assertFalse($vMonitor->refreshStatus($vLater));
        self::assertSame(MonitorStatus::Down, $vMonitor->getStatus());
    }

    public function testPausedMonitorStaysPaused(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Success, $this->aNow);

        $vMonitor->pause();
        $vMonitor->refreshStatus($this->aNow->modify('+1 day'));

        self::assertSame(MonitorStatus::Paused, $vMonitor->getStatus());
    }

    public function testResumeStartsFromScratch(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Success, $this->aNow);
        $vMonitor->pause();

        $vMonitor->resume();
        $vMonitor->refreshStatus($this->aNow->modify('+1 day'));

        self::assertSame(MonitorStatus::New, $vMonitor->getStatus());
        self::assertNull($vMonitor->getLastPingAt());
    }

    public function testRotatePingTokenStoresOnlyItsHash(): void
    {
        $vMonitor = $this->createMonitor();

        $vFirstToken = $vMonitor->rotatePingToken();
        $vSecondToken = $vMonitor->rotatePingToken();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $vFirstToken);
        self::assertNotSame($vFirstToken, $vSecondToken);
        self::assertSame(hash('sha256', $vSecondToken), Monitor::hashToken($vSecondToken));
    }

    public function testActiveCheckIsDueAfterInterval(): void
    {
        $vMonitor = new Monitor('Site', MonitorType::Http, self::INTERVAL, self::GRACE, $this->aNow);

        self::assertTrue($vMonitor->isCheckDue($this->aNow));
        $vMonitor->recordPing(PingKind::Success, $this->aNow);
        self::assertFalse($vMonitor->isCheckDue($this->aNow->modify('+'.(self::INTERVAL - 1).' seconds')));
        self::assertTrue($vMonitor->isCheckDue($this->aNow->modify('+'.self::INTERVAL.' seconds')));

        $vMonitor->pause();
        self::assertFalse($vMonitor->isCheckDue($this->aNow->modify('+1 day')));
    }

    public function testHeartbeatIsNeverCheckedActively(): void
    {
        self::assertFalse($this->createMonitor()->isCheckDue($this->aNow->modify('+1 day')));
    }

    private function createMonitor(): Monitor
    {
        $rMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, self::INTERVAL, self::GRACE, $this->aNow);

        return $rMonitor;
    }
}
