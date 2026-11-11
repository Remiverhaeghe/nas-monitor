<?php

namespace App\Tests\Check;

/**
 * Fabrique un flux dont le contexte porte un certificat « présenté par le serveur » ;
 * seuls les flux socket conservent le contexte qu'on leur donne.
 */
trait CertificateStreamTrait
{
    /**
     * @var list<resource>
     */
    private array $aOpenServers = [];

    /**
     * @return resource
     */
    private function openStreamWithCertificate(mixed $pCertificate)
    {
        $vServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vServer);
        $this->aOpenServers[] = $vServer;

        $rStream = stream_socket_client(
            'tcp://'.stream_socket_get_name($vServer, false),
            $vErrorCode,
            $vErrorMessage,
            2,
            \STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['peer_certificate' => $pCertificate]]),
        );
        self::assertNotFalse($rStream);

        return $rStream;
    }

    protected function tearDown(): void
    {
        foreach ($this->aOpenServers as $vServer) {
            fclose($vServer);
        }
        $this->aOpenServers = [];
    }
}
