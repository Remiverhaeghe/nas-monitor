<?php

namespace App\Check;

final class StreamTlsConnector implements TlsConnector
{
    /**
     * @return array{ssl: array<string, bool|string>}
     */
    public static function contextOptions(string $pHost): array
    {
        $rOptions = ['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $pHost,
            'SNI_enabled' => true,
        ]];

        return $rOptions;
    }

    public function connect(string $pHost, int $pPort, int $pTimeoutSeconds)
    {
        $vContext = stream_context_create(self::contextOptions($pHost));

        return @stream_socket_client(
            \sprintf('ssl://%s:%d', $pHost, $pPort),
            $vErrorCode,
            $vErrorMessage,
            $pTimeoutSeconds,
            \STREAM_CLIENT_CONNECT,
            $vContext,
        ) ?: throw new \RuntimeException(\sprintf('Connexion TLS impossible (%d) : %s', $vErrorCode, $vErrorMessage));
    }
}
