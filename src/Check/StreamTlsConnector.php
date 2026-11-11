<?php

namespace App\Check;

final class StreamTlsConnector implements TlsConnector
{
    public function connect(string $pHost, int $pPort, int $pTimeoutSeconds)
    {
        $vContext = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $pHost,
            'SNI_enabled' => true,
        ]]);

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
