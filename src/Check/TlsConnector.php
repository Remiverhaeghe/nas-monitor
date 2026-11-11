<?php

namespace App\Check;

/**
 * Ouvre une connexion TLS vérifiée et rend le flux, dont le contexte conserve le certificat du serveur.
 */
interface TlsConnector
{
    /**
     * @return resource
     *
     * @throws \RuntimeException avec un message lisible quand la connexion ou la vérification échoue
     */
    public function connect(string $pHost, int $pPort, int $pTimeoutSeconds);
}
