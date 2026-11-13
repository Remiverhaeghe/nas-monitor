<?php

namespace App\Check;

final class NativeDatagramSocket implements DatagramSocket
{
    private readonly \Socket $aSocket;

    /**
     * @param int $pProtocol 1 pour ICMP, 17 pour UDP
     */
    public function __construct(int $pProtocol)
    {
        $this->aSocket = @socket_create(\AF_INET, \SOCK_DGRAM, $pProtocol) ?: throw new \RuntimeException('Socket ICMP indisponible : '.socket_strerror(socket_last_error()));
    }

    public function setReceiveTimeout(int $pSeconds): void
    {
        socket_set_option($this->aSocket, \SOL_SOCKET, \SO_RCVTIMEO, ['sec' => $pSeconds, 'usec' => 0]);
    }

    public function send(string $pPacket, string $pAddress, int $pPort): void
    {
        @socket_sendto($this->aSocket, $pPacket, \strlen($pPacket), 0, $pAddress, $pPort) ?: throw new \RuntimeException('Envoi du ping impossible : '.socket_strerror(socket_last_error($this->aSocket)));
    }

    public function receive(int $pLength): ?string
    {
        $vData = '';
        $vFrom = '';
        $vPort = 0;
        $rData = @socket_recvfrom($this->aSocket, $vData, $pLength, 0, $vFrom, $vPort) ? $vData : null;

        return $rData;
    }

    public function close(): void
    {
        socket_close($this->aSocket);
    }
}
