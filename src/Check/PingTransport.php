<?php

namespace App\Check;

/**
 * Envoie une requête d'écho ICMP et rend la réponse brute ; isolé pour pouvoir simuler les pannes du système.
 */
interface PingTransport
{
    /**
     * @throws \RuntimeException avec un message lisible quand le socket, l'envoi ou la réponse échoue
     */
    public function echo(string $pAddress, string $pPacket, int $pTimeoutSeconds): string;
}
