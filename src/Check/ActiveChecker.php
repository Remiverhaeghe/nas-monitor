<?php

namespace App\Check;

use App\Entity\Monitor;
use App\Enum\MonitorType;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ActiveChecker
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HttpClientInterface $aHttpClient,
        private readonly ClockInterface $aClock,
        #[Autowire(env: 'int:TLS_WARNING_DAYS')]
        private readonly int $aTlsWarningDays,
    ) {
    }

    public function check(Monitor $pMonitor): CheckResult
    {
        $vTarget = (string) $pMonitor->getTarget();
        $vStart = hrtime(true);

        [$vSuccess, $vMessage] = match ($pMonitor->getType()) {
            MonitorType::Http => $this->checkHttp($vTarget),
            MonitorType::Tcp => $this->checkTcp($vTarget),
            MonitorType::Tls => $this->checkTls($vTarget),
            MonitorType::Heartbeat => throw new \LogicException('Un moniteur heartbeat ne se contrôle pas activement.'),
        };

        $rResult = new CheckResult($vSuccess, intdiv(hrtime(true) - $vStart, 1_000_000), $vMessage);

        return $rResult;
    }

    /**
     * @return array{bool, string}
     */
    public static function evaluateCertificate(int $pValidToTimestamp, \DateTimeImmutable $pNow, int $pWarningDays): array
    {
        $vDaysLeft = intdiv($pValidToTimestamp - $pNow->getTimestamp(), 86400);
        $vExpiry = (new \DateTimeImmutable('@'.$pValidToTimestamp))->format('d/m/Y');

        $rEvaluation = match (true) {
            $vDaysLeft < 0 => [false, 'Certificat expiré depuis le '.$vExpiry],
            $vDaysLeft < $pWarningDays => [false, \sprintf('Certificat expirant le %s (%d jours)', $vExpiry, $vDaysLeft)],
            default => [true, \sprintf('Certificat valide jusqu\'au %s (%d jours)', $vExpiry, $vDaysLeft)],
        };

        return $rEvaluation;
    }

    /**
     * @return array{string, int}
     */
    public static function splitHostPort(string $pTarget): array
    {
        $vSeparator = strrpos($pTarget, ':');
        if (false === $vSeparator) {
            throw new \InvalidArgumentException('Cible attendue au format hôte:port.');
        }

        $rParts = [substr($pTarget, 0, $vSeparator), (int) substr($pTarget, $vSeparator + 1)];

        return $rParts;
    }

    /**
     * @return array{bool, string}
     */
    private function checkHttp(string $pUrl): array
    {
        try {
            $vResponse = $this->aHttpClient->request('GET', $pUrl, [
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS * 2,
                'max_redirects' => 3,
            ]);
            $vStatusCode = $vResponse->getStatusCode();
        } catch (ExceptionInterface $vException) {
            return [false, 'Erreur HTTP : '.$vException->getMessage()];
        }

        $rResult = [$vStatusCode < 400, 'HTTP '.$vStatusCode];

        return $rResult;
    }

    /**
     * @return array{bool, string}
     */
    private function checkTcp(string $pTarget): array
    {
        [$vHost, $vPort] = self::splitHostPort($pTarget);
        $vSocket = @stream_socket_client(\sprintf('tcp://%s:%d', $vHost, $vPort), $vErrorCode, $vErrorMessage, self::TIMEOUT_SECONDS);
        if (false === $vSocket) {
            return [false, \sprintf('Connexion refusée (%d) : %s', $vErrorCode, $vErrorMessage)];
        }

        fclose($vSocket);
        $rResult = [true, 'Port ouvert'];

        return $rResult;
    }

    /**
     * @return array{bool, string}
     */
    private function checkTls(string $pTarget): array
    {
        [$vHost, $vPort] = self::splitHostPort($pTarget);
        $vContext = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $vHost,
            'SNI_enabled' => true,
        ]]);

        $vSocket = @stream_socket_client(
            \sprintf('ssl://%s:%d', $vHost, $vPort),
            $vErrorCode,
            $vErrorMessage,
            self::TIMEOUT_SECONDS,
            \STREAM_CLIENT_CONNECT,
            $vContext,
        );
        if (false === $vSocket) {
            return [false, \sprintf('Connexion TLS impossible (%d) : %s', $vErrorCode, $vErrorMessage)];
        }

        $vParams = stream_context_get_params($vSocket);
        fclose($vSocket);
        $vCertificate = openssl_x509_parse($vParams['options']['ssl']['peer_certificate']);
        if (false === $vCertificate) {
            return [false, 'Certificat illisible'];
        }

        $rResult = self::evaluateCertificate((int) $vCertificate['validTo_time_t'], $this->aClock->now(), $this->aTlsWarningDays);

        return $rResult;
    }
}
