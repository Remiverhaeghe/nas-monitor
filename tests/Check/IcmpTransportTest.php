<?php

namespace App\Tests\Check;

use App\Check\ActiveChecker;
use App\Check\CheckResult;
use App\Check\PingTransport;
use App\Check\SocketPingTransport;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

class IcmpTransportTest extends TestCase
{
    private function checkWith(PingTransport $pTransport): CheckResult
    {
        $vNow = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vMonitor = new Monitor('NAS', MonitorType::Icmp, 300, 60, $vNow);
        $vMonitor->setTarget('192.168.1.10');

        return (new ActiveChecker(new MockHttpClient(), new MockClock($vNow), 14, $pTransport))->check($vMonitor);
    }

    private function transportReturning(string $pReply): PingTransport
    {
        $vTransport = $this->createStub(PingTransport::class);
        $vTransport->method('echo')->willReturn($pReply);

        return $vTransport;
    }

    public function testEchoReplyMeansSuccess(): void
    {
        $vResult = $this->checkWith($this->transportReturning("\x00\x00"));

        self::assertTrue($vResult->success);
        self::assertSame('Réponse de 192.168.1.10', $vResult->message);
    }

    public function testUnexpectedReplyTypeIsAFailure(): void
    {
        $vResult = $this->checkWith($this->transportReturning("\x03\x01"));

        self::assertFalse($vResult->success);
        self::assertStringContainsString('type 3', $vResult->message);
    }

    public function testEmptyReplyIsAFailure(): void
    {
        $vResult = $this->checkWith($this->transportReturning(''));

        self::assertFalse($vResult->success);
        self::assertStringContainsString('type -1', $vResult->message);
    }

    public function testTransportErrorsBecomeTheFailureMessage(): void
    {
        $vTransport = $this->createStub(PingTransport::class);
        $vTransport->method('echo')->willThrowException(new \RuntimeException('Pas de réponse de 192.168.1.10 en 3 s'));

        $vResult = $this->checkWith($vTransport);

        self::assertFalse($vResult->success);
        self::assertSame('Pas de réponse de 192.168.1.10 en 3 s', $vResult->message);
    }

    public function testRealTransportAnswersOrExplainsWhyNot(): void
    {
        try {
            $vReply = (new SocketPingTransport())->echo('127.0.0.1', ActiveChecker::buildEchoRequest(1), 1);
            self::assertNotSame('', $vReply);
        } catch (\RuntimeException $vException) {
            self::assertNotSame('', $vException->getMessage());
        }
    }
}
