<?php

namespace App\Check;

/**
 * Socket ICMP « datagramme » : pas besoin de droits root, Docker l'autorise par défaut (net.ipv4.ping_group_range).
 */
final class SocketPingTransport implements PingTransport
{
    public function echo(string $pAddress, string $pPacket, int $pTimeoutSeconds): string
    {
        $vSocket = @socket_create(\AF_INET, \SOCK_DGRAM, 1) ?: throw new \RuntimeException('Socket ICMP indisponible : '.socket_strerror(socket_last_error()));

        try {
            socket_set_option($vSocket, \SOL_SOCKET, \SO_RCVTIMEO, ['sec' => $pTimeoutSeconds, 'usec' => 0]);
            @socket_sendto($vSocket, $pPacket, \strlen($pPacket), 0, $pAddress, 0) ?: throw new \RuntimeException('Envoi du ping impossible : '.socket_strerror(socket_last_error($vSocket)));

            $vReply = '';
            $vFrom = '';
            $vPort = 0;
            @socket_recvfrom($vSocket, $vReply, 1500, 0, $vFrom, $vPort) ?: throw new \RuntimeException(\sprintf('Pas de réponse de %s en %d s', $pAddress, $pTimeoutSeconds));
        } finally {
            socket_close($vSocket);
        }

        return $vReply;
    }
}
