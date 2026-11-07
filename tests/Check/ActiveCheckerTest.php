<?php

namespace App\Tests\Check;

use App\Check\ActiveChecker;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ActiveCheckerTest extends TestCase
{
    private const NOW = '2026-11-07 12:00:00';

    public function testValidCertificate(): void
    {
        [$vSuccess, $vMessage] = ActiveChecker::evaluateCertificate($this->inDays(60), new \DateTimeImmutable(self::NOW), 14);

        self::assertTrue($vSuccess);
        self::assertStringContainsString('60 jours', $vMessage);
    }

    public function testCertificateCloseToExpiryFails(): void
    {
        [$vSuccess, $vMessage] = ActiveChecker::evaluateCertificate($this->inDays(10), new \DateTimeImmutable(self::NOW), 14);

        self::assertFalse($vSuccess);
        self::assertStringContainsString('expirant', $vMessage);
    }

    public function testExpiredCertificateFails(): void
    {
        [$vSuccess, $vMessage] = ActiveChecker::evaluateCertificate($this->inDays(-2), new \DateTimeImmutable(self::NOW), 14);

        self::assertFalse($vSuccess);
        self::assertStringContainsString('expiré', $vMessage);
    }

    public function testSplitHostPort(): void
    {
        self::assertSame(['nas.local', 443], ActiveChecker::splitHostPort('nas.local:443'));
    }

    public function testHttpSuccess(): void
    {
        $vResult = $this->createChecker(new MockResponse('ok', ['http_code' => 200]))->check($this->createMonitor(MonitorType::Http, 'https://nas.local'));

        self::assertTrue($vResult->success);
        self::assertSame('HTTP 200', $vResult->message);
    }

    public function testHttpServerErrorFails(): void
    {
        $vResult = $this->createChecker(new MockResponse('', ['http_code' => 503]))->check($this->createMonitor(MonitorType::Http, 'https://nas.local'));

        self::assertFalse($vResult->success);
        self::assertSame('HTTP 503', $vResult->message);
    }

    public function testHttpNetworkErrorFails(): void
    {
        $vResult = $this->createChecker(new MockResponse('', ['error' => 'Could not resolve host']))->check($this->createMonitor(MonitorType::Http, 'https://nas.local'));

        self::assertFalse($vResult->success);
        self::assertStringContainsString('Could not resolve host', $vResult->message);
    }

    public function testTcpOpenAndClosedPort(): void
    {
        $vServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vServer);
        $vAddress = (string) stream_socket_get_name($vServer, false);
        $vChecker = $this->createChecker(new MockResponse());

        self::assertTrue($vChecker->check($this->createMonitor(MonitorType::Tcp, $vAddress))->success);

        fclose($vServer);
        self::assertFalse($vChecker->check($this->createMonitor(MonitorType::Tcp, $vAddress))->success);
    }

    private function createChecker(MockResponse $pResponse): ActiveChecker
    {
        $rChecker = new ActiveChecker(new MockHttpClient($pResponse), new MockClock(self::NOW), 14);

        return $rChecker;
    }

    private function createMonitor(MonitorType $pType, string $pTarget): Monitor
    {
        $rMonitor = new Monitor('Contrôle', $pType, 300, 60, new \DateTimeImmutable(self::NOW));
        $rMonitor->setTarget($pTarget);

        return $rMonitor;
    }

    private function inDays(int $pDays): int
    {
        $rTimestamp = (new \DateTimeImmutable(self::NOW))->modify(\sprintf('%+d days', $pDays))->getTimestamp() + 3600;

        return $rTimestamp;
    }
}
