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
    private const ICMP_TIMEOUT_SECONDS = 3;
    private const ICMP_PAYLOAD = 'nas-monitor';

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
            MonitorType::Dns => $this->checkDns($vTarget),
            MonitorType::Icmp => $this->checkIcmp($vTarget),
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
     * Cible « nom » ou « nom=adresse attendue ».
     *
     * @param list<string> $pAddresses
     *
     * @return array{bool, string}
     */
    public static function evaluateDns(string $pTarget, array $pAddresses): array
    {
        [$vHost, $vExpected] = array_pad(explode('=', $pTarget, 2), 2, null);

        $rEvaluation = match (true) {
            [] === $pAddresses => [false, 'Aucune adresse pour '.$vHost],
            null !== $vExpected && !\in_array($vExpected, $pAddresses, true) => [false, \sprintf('%s résout vers %s au lieu de %s', $vHost, implode(', ', $pAddresses), $vExpected)],
            default => [true, $vHost.' résout vers '.implode(', ', $pAddresses)],
        };

        return $rEvaluation;
    }

    /**
     * Requête d'écho ICMP (type 8) avec somme de contrôle RFC 1071.
     */
    public static function buildEchoRequest(int $pSequence): string
    {
        $vUnsigned = pack('CCnnn', 8, 0, 0, 0, $pSequence).self::ICMP_PAYLOAD;
        $vData = 0 === \strlen($vUnsigned) % 2 ? $vUnsigned : $vUnsigned."\0";
        $vSum = array_sum(unpack('n*', $vData) ?: []);
        while ($vSum > 0xFFFF) {
            $vSum = ($vSum & 0xFFFF) + ($vSum >> 16);
        }

        $rPacket = pack('CCnnn', 8, 0, ~$vSum & 0xFFFF, 0, $pSequence).self::ICMP_PAYLOAD;

        return $rPacket;
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
    private function checkDns(string $pTarget): array
    {
        $vHost = explode('=', $pTarget, 2)[0];
        $vAddresses = [];
        foreach (@dns_get_record($vHost, \DNS_A | \DNS_AAAA) ?: [] as $vRecord) {
            $vAddresses[] = (string) ($vRecord['ip'] ?? $vRecord['ipv6'] ?? '');
        }

        $rResult = self::evaluateDns($pTarget, array_values(array_filter($vAddresses)));

        return $rResult;
    }

    /**
     * Socket ICMP « datagramme » : pas besoin de droits root, Docker l'autorise par défaut (net.ipv4.ping_group_range).
     *
     * @return array{bool, string}
     */
    private function checkIcmp(string $pHost): array
    {
        $vAddress = false !== filter_var($pHost, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) ? $pHost : gethostbyname($pHost);
        if (false === filter_var($vAddress, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return [false, 'Nom introuvable : '.$pHost];
        }

        $vSocket = @socket_create(\AF_INET, \SOCK_DGRAM, 1);
        if (false === $vSocket) {
            return [false, 'Socket ICMP indisponible : '.socket_strerror(socket_last_error())];
        }

        try {
            socket_set_option($vSocket, \SOL_SOCKET, \SO_RCVTIMEO, ['sec' => self::ICMP_TIMEOUT_SECONDS, 'usec' => 0]);
            $vPacket = self::buildEchoRequest(random_int(1, 0xFFFF));
            if (false === @socket_sendto($vSocket, $vPacket, \strlen($vPacket), 0, $vAddress, 0)) {
                return [false, 'Envoi du ping impossible : '.socket_strerror(socket_last_error($vSocket))];
            }

            $vReply = '';
            $vFrom = '';
            $vPort = 0;
            if (false === @socket_recvfrom($vSocket, $vReply, 1500, 0, $vFrom, $vPort)) {
                return [false, \sprintf('Pas de réponse de %s en %d s', $vAddress, self::ICMP_TIMEOUT_SECONDS)];
            }
        } finally {
            socket_close($vSocket);
        }

        $vType = '' === $vReply ? -1 : \ord($vReply[0]);
        $rResult = 0 === $vType ? [true, 'Réponse de '.$vAddress] : [false, \sprintf('Réponse ICMP inattendue de %s (type %d)', $vAddress, $vType)];

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
