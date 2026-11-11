<?php

namespace App\Tests\Check;

use App\Check\ActiveChecker;
use App\Check\CheckResult;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

class ActiveCheckerEdgeCasesTest extends TestCase
{
    use CertificateStreamTrait;

    private function checkTarget(MonitorType $pType, string $pTarget): CheckResult
    {
        $vNow = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vMonitor = new Monitor('Cible', $pType, 3600, 600, $vNow);
        $vMonitor->setTarget($pTarget);

        return (new ActiveChecker(new MockHttpClient(), new MockClock($vNow), 14))->check($vMonitor);
    }

    public function testHeartbeatCannotBeCheckedActively(): void
    {
        $this->expectException(\LogicException::class);
        $this->checkTarget(MonitorType::Heartbeat, '');
    }

    public function testTargetWithoutPortIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ActiveChecker::splitHostPort('nas.local');
    }

    public function testHostAndPortAreSplitOnTheLastColon(): void
    {
        self::assertSame(['nas.local', 443], ActiveChecker::splitHostPort('nas.local:443'));
    }

    public function testDnsCheckReturnsAResultForLocalhost(): void
    {
        $vResult = $this->checkTarget(MonitorType::Dns, 'localhost');

        self::assertGreaterThanOrEqual(0, $vResult->durationMs);
        self::assertNotSame('', $vResult->message);
    }

    public function testIcmpToUnknownNameFails(): void
    {
        $vResult = $this->checkTarget(MonitorType::Icmp, 'introuvable.invalid');

        self::assertFalse($vResult->success);
        self::assertStringContainsString('Nom introuvable', $vResult->message);
    }

    public function testIcmpToLoopbackReturnsAResult(): void
    {
        $vResult = $this->checkTarget(MonitorType::Icmp, '127.0.0.1');

        self::assertNotSame('', $vResult->message);
    }

    public function testTlsToClosedPortFails(): void
    {
        $vServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vServer);
        $vAddress = (string) stream_socket_get_name($vServer, false);
        fclose($vServer);

        $vResult = $this->checkTarget(MonitorType::Tls, $vAddress);

        self::assertFalse($vResult->success);
        self::assertStringContainsString('Connexion TLS impossible', $vResult->message);
    }

    public function testAddressesAreExtractedFromDnsRecords(): void
    {
        self::assertSame(
            ['192.168.1.10', '2001:db8::1'],
            ActiveChecker::extractAddresses([['ip' => '192.168.1.10'], ['ipv6' => '2001:db8::1'], ['target' => 'sans-adresse']]),
        );
    }

    public function testPeerCertificateIsReadFromTheStream(): void
    {
        $vCertificate = openssl_x509_read((string) file_get_contents(__DIR__.'/../fixtures/test-certificate.pem'));

        [$vSuccess, $vMessage] = $this->checker()->evaluatePeerCertificate($this->openStreamWithCertificate($vCertificate));

        self::assertTrue($vSuccess);
        self::assertStringContainsString('Certificat valide', $vMessage);
    }

    public function testUnreadableCertificateIsReported(): void
    {
        $vStream = $this->openStreamWithCertificate('pas un certificat');

        set_error_handler(static fn (): bool => true);
        try {
            [$vSuccess, $vMessage] = $this->checker()->evaluatePeerCertificate($vStream);
        } finally {
            restore_error_handler();
        }

        self::assertFalse($vSuccess);
        self::assertSame('Certificat illisible', $vMessage);
    }

    private function checker(): ActiveChecker
    {
        return new ActiveChecker(new MockHttpClient(), new MockClock(new \DateTimeImmutable()), 14);
    }
}
