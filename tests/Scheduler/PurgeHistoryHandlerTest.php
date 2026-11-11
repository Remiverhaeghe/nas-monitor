<?php

namespace App\Tests\Scheduler;

use App\Repository\AuditLogRepository;
use App\Repository\PingRepository;
use App\Scheduler\PurgeHistoryHandler;
use App\Scheduler\PurgeHistoryMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

class PurgeHistoryHandlerTest extends TestCase
{
    public function testPurgesBothHistoriesWithTheirOwnRetention(): void
    {
        $vPings = $this->createMock(PingRepository::class);
        $vPings->expects(self::once())->method('deleteReceivedBefore')
            ->with(new \DateTimeImmutable('2026-11-01 12:00:00'))->willReturn(3);
        $vAudit = $this->createMock(AuditLogRepository::class);
        $vAudit->expects(self::once())->method('deleteOccurredBefore')
            ->with(new \DateTimeImmutable('2026-10-12 12:00:00'))->willReturn(1);

        (new PurgeHistoryHandler($vPings, $vAudit, new MockClock('2026-11-11 12:00:00'), new NullLogger(), 10, 30))(new PurgeHistoryMessage());
    }

    public function testFailureOfOnePurgeDoesNotStopTheOther(): void
    {
        $vPings = $this->createStub(PingRepository::class);
        $vPings->method('deleteReceivedBefore')->willThrowException(new \RuntimeException('base indisponible'));
        $vAudit = $this->createMock(AuditLogRepository::class);
        $vAudit->expects(self::once())->method('deleteOccurredBefore')->willReturn(0);

        (new PurgeHistoryHandler($vPings, $vAudit, new MockClock('2026-11-11 12:00:00'), new NullLogger(), 10, 30))(new PurgeHistoryMessage());
    }

    public function testAuditFailureIsLoggedWithoutThrowing(): void
    {
        $vPings = $this->createStub(PingRepository::class);
        $vAudit = $this->createStub(AuditLogRepository::class);
        $vAudit->method('deleteOccurredBefore')->willThrowException(new \RuntimeException('erreur'));

        (new PurgeHistoryHandler($vPings, $vAudit, new MockClock('2026-11-11 12:00:00'), new NullLogger(), 10, 30))(new PurgeHistoryMessage());

        $this->addToAssertionCount(1);
    }
}
