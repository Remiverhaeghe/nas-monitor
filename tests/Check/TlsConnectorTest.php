<?php

namespace App\Tests\Check;

use App\Check\ActiveChecker;
use App\Check\SocketPingTransport;
use App\Check\StreamTlsConnector;
use App\Check\TlsConnector;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

class TlsConnectorTest extends TestCase
{
    use CertificateStreamTrait;

    private function monitor(): Monitor
    {
        $vMonitor = new Monitor('Certificat', MonitorType::Tls, 3600, 600, new \DateTimeImmutable('2026-11-11 12:00:00'));
        $vMonitor->setTarget('nas.exemple.fr:443');

        return $vMonitor;
    }

    private function checker(TlsConnector $pConnector): ActiveChecker
    {
        return new ActiveChecker(new MockHttpClient(), new MockClock('2026-11-11 12:00:00'), 14, new SocketPingTransport(), $pConnector);
    }

    public function testValidCertificateMakesTheCheckSucceed(): void
    {
        $vCertificate = openssl_x509_read((string) file_get_contents(__DIR__.'/../fixtures/test-certificate.pem'));
        $vConnector = $this->createStub(TlsConnector::class);
        $vConnector->method('connect')->willReturn($this->openStreamWithCertificate($vCertificate));

        $vResult = $this->checker($vConnector)->check($this->monitor());

        self::assertTrue($vResult->success);
        self::assertStringContainsString('Certificat valide', $vResult->message);
    }

    public function testConnectionErrorBecomesTheFailureMessage(): void
    {
        $vConnector = $this->createStub(TlsConnector::class);
        $vConnector->method('connect')->willThrowException(new \RuntimeException('Connexion TLS impossible (111) : refusée'));

        $vResult = $this->checker($vConnector)->check($this->monitor());

        self::assertFalse($vResult->success);
        self::assertSame('Connexion TLS impossible (111) : refusée', $vResult->message);
    }

    public function testRealConnectorRefusesAClosedPort(): void
    {
        $vServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vServer);
        [$vHost, $vPort] = ActiveChecker::splitHostPort((string) stream_socket_get_name($vServer, false));
        fclose($vServer);

        $this->expectException(\RuntimeException::class);
        (new StreamTlsConnector())->connect($vHost, $vPort, 2);
    }

    public function testPeerIsVerifiedAndItsCertificateCaptured(): void
    {
        self::assertSame(
            ['ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => 'nas.exemple.fr',
                'SNI_enabled' => true,
            ]],
            StreamTlsConnector::contextOptions('nas.exemple.fr'),
        );
    }
}
