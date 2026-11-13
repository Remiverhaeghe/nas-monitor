<?php

namespace App\Check;

/**
 * Socket de datagrammes : isole les fonctions socket_* pour pouvoir tester le contrôle ping sans réseau.
 */
interface DatagramSocket
{
    public function setReceiveTimeout(int $pSeconds): void;

    /**
     * @throws \RuntimeException si l'envoi échoue
     */
    public function send(string $pPacket, string $pAddress, int $pPort): void;

    /**
     * Retourne null si rien n'est reçu avant le délai.
     */
    public function receive(int $pLength): ?string;

    public function close(): void;
}
