<?php

namespace App\Tests\Check;

use App\Check\ActiveChecker;
use App\Check\CheckResult;
use App\Check\DnsResolver;
use App\Check\PingTransport;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Valeurs exactes : messages, bornes et octets du paquet, pour que les mutations ne passent pas.
 */
class ActiveCheckerMutationTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @return iterable<string, array{int, int, bool, string}>
     */
    public static function provideCertificates(): iterable
    {
        $vDay = 86400;

        yield 'expiré depuis deux jours' => [-2 * $vDay, 30, false, 'Certificat expiré depuis le %s'];
        yield 'expire aujourd\'hui' => [3600, 30, false, 'Certificat expirant le %s (0 jours)'];
        yield 'expire bientôt' => [10 * $vDay, 30, false, 'Certificat expirant le %s (10 jours)'];
        yield 'pile à la limite' => [10 * $vDay, 10, true, 'Certificat valide jusqu\'au %s (10 jours)'];
        yield 'presque six jours' => [518_399, 3, true, 'Certificat valide jusqu\'au %s (5 jours)'];
        yield 'cinq jours pleins' => [5 * $vDay, 3, true, 'Certificat valide jusqu\'au %s (5 jours)'];
    }

    #[DataProvider('provideCertificates')]
    public function testCertificateEvaluation(int $pDelta, int $pWarningDays, bool $pSuccess, string $pFormat): void
    {
        $vNow = (new \DateTimeImmutable('@'.self::NOW));
        $vValidTo = self::NOW + $pDelta;

        $vResult = ActiveChecker::evaluateCertificate($vValidTo, $vNow, $pWarningDays);

        self::assertSame([$pSuccess, \sprintf($pFormat, gmdate('d/m/Y', $vValidTo))], $vResult);
    }

    public function testDnsEvaluationMessages(): void
    {
        self::assertSame([false, 'Aucune adresse pour h'], ActiveChecker::evaluateDns('h', []));
        self::assertSame(
            [false, 'h résout vers 2.2.2.2, 3.3.3.3 au lieu de 1.1.1.1'],
            ActiveChecker::evaluateDns('h=1.1.1.1', ['2.2.2.2', '3.3.3.3']),
        );
        self::assertSame(
            [true, 'h résout vers 1.1.1.1, 2.2.2.2'],
            ActiveChecker::evaluateDns('h', ['1.1.1.1', '2.2.2.2']),
        );
        self::assertSame(
            [false, 'h résout vers 1.1.1.1 au lieu de 1.1.1.1=x'],
            ActiveChecker::evaluateDns('h=1.1.1.1=x', ['1.1.1.1']),
        );
    }

    public function testExtractedAddressesKeepIpv4FirstAndAreReindexed(): void
    {
        $vRecords = [['type' => 'A'], ['ip' => '1.1.1.1', 'ipv6' => '::1'], ['ipv6' => '2001:db8::1'], ['ip' => 1234]];

        self::assertSame(['1.1.1.1', '2001:db8::1', '1234'], ActiveChecker::extractAddresses($vRecords));
    }

    public function testEchoRequestBytesAreExact(): void
    {
        self::assertSame('08005427000000016e61732d6d6f6e69746f72', bin2hex(ActiveChecker::buildEchoRequest(1)));
        self::assertSame('080041f4000012346e61732d6d6f6e69746f72', bin2hex(ActiveChecker::buildEchoRequest(0x1234)));
    }

    /**
     * @param callable(string, string, array<string, mixed>): MockResponse $pResponder
     */
    private function httpChecker(callable $pResponder): ActiveChecker
    {
        return new ActiveChecker(new MockHttpClient($pResponder), new MockClock('2026-11-11 12:00:00'), 14);
    }

    private function monitor(MonitorType $pType, ?string $pTarget): Monitor
    {
        $vMonitor = new Monitor('Cible', $pType, 3600, 600, new \DateTimeImmutable('2026-11-11 12:00:00'));
        if (null !== $pTarget) {
            $vMonitor->setTarget($pTarget);
        }

        return $vMonitor;
    }

    public function testHttpRequestOptionsAndStatusLimit(): void
    {
        $vOptions = [];
        $vChecker = $this->httpChecker(static function (string $pMethod, string $pUrl, array $pOptions) use (&$vOptions): MockResponse {
            $vOptions = $pOptions;

            return new MockResponse('', ['http_code' => 400]);
        });

        $vResult = $vChecker->check($this->monitor(MonitorType::Http, 'https://nas.exemple.fr'));

        self::assertFalse($vResult->success);
        self::assertSame('HTTP 400', $vResult->message);
        self::assertEquals(10, $vOptions['timeout']);
        self::assertEquals(20, $vOptions['max_duration']);
        self::assertSame(3, $vOptions['max_redirects']);
        self::assertLessThan(5000, $vResult->durationMs);
        self::assertGreaterThanOrEqual(0, $vResult->durationMs);
    }

    public function testHttpSuccessAndTransportError(): void
    {
        $vOk = $this->httpChecker(static fn (): MockResponse => new MockResponse('', ['http_code' => 399]));
        self::assertTrue($vOk->check($this->monitor(MonitorType::Http, 'https://a.fr'))->success);

        $vKo = $this->httpChecker(static fn (): MockResponse => new MockResponse('', ['error' => 'boom']));
        $vResult = $vKo->check($this->monitor(MonitorType::Http, 'https://a.fr'));

        self::assertFalse($vResult->success);
        self::assertStringStartsWith('Erreur HTTP : ', $vResult->message);
        self::assertStringContainsString('boom', $vResult->message);
    }

    public function testMissingTargetIsAnInvalidArgumentNotATypeError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->httpChecker(static fn (): MockResponse => new MockResponse())->check($this->monitor(MonitorType::Tcp, null));
    }

    /**
     * @return PingTransport&object{address: string|null}
     */
    private function icmpTransport(string $pReply): PingTransport
    {
        return new class($pReply) implements PingTransport {
            public ?string $address = null;

            public function __construct(private readonly string $aReply)
            {
            }

            public function echo(string $pAddress, string $pPacket, int $pTimeoutSeconds): string
            {
                $this->address = $pAddress;

                return $this->aReply;
            }
        };
    }

    private function icmpResult(PingTransport $pTransport, string $pTarget): CheckResult
    {
        $vChecker = new ActiveChecker(new MockHttpClient(), new MockClock('2026-11-11 12:00:00'), 14, $pTransport);

        return $vChecker->check($this->monitor(MonitorType::Icmp, $pTarget));
    }

    public function testIcmpResolvesNamesAndReportsReplyTypes(): void
    {
        $vTransport = $this->icmpTransport("\x00\x00");
        $vResult = $this->icmpResult($vTransport, 'localhost');
        self::assertSame('127.0.0.1', $vTransport->address);
        self::assertSame([true, 'Réponse de 127.0.0.1'], [$vResult->success, $vResult->message]);

        $vResult = $this->icmpResult($this->icmpTransport("\x03\x00"), '10.0.0.1');
        self::assertSame([false, 'Réponse ICMP inattendue de 10.0.0.1 (type 3)'], [$vResult->success, $vResult->message]);

        $vResult = $this->icmpResult($this->icmpTransport(''), '10.0.0.1');
        self::assertSame('Réponse ICMP inattendue de 10.0.0.1 (type -1)', $vResult->message);

        $vResult = $this->icmpResult($this->icmpTransport(''), 'introuvable.invalid');
        self::assertSame('Nom introuvable : introuvable.invalid', $vResult->message);
    }

    public function testDnsLooksUpOnlyTheHostPartOfTheTarget(): void
    {
        $vResolver = new class implements DnsResolver {
            public ?string $host = null;

            public function lookup(string $pHost): array
            {
                $this->host = $pHost;

                return [['ip' => '1.1.1.1']];
            }
        };
        $vChecker = new ActiveChecker(new MockHttpClient(), new MockClock('2026-11-11 12:00:00'), 14, aDnsResolver: $vResolver);

        $vResult = $vChecker->check($this->monitor(MonitorType::Dns, 'nas.exemple.fr=1.1.1.1'));

        self::assertSame('nas.exemple.fr', $vResolver->host);
        self::assertSame([true, 'nas.exemple.fr résout vers 1.1.1.1'], [$vResult->success, $vResult->message]);
    }
}
