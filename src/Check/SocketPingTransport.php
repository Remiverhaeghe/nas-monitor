<?php

namespace App\Check;

final class SocketPingTransport implements PingTransport
{
    private const RECEIVE_LENGTH = 1500;

    public function __construct(
        private readonly DatagramSocketFactory $aSocketFactory = new NativeDatagramSocketFactory(),
    ) {
    }

    public function echo(string $pAddress, string $pPacket, int $pTimeoutSeconds): string
    {
        $vSocket = $this->aSocketFactory->open();

        try {
            $vSocket->setReceiveTimeout($pTimeoutSeconds);
            $vSocket->send($pPacket, $pAddress, 0);
            $rReply = $vSocket->receive(self::RECEIVE_LENGTH) ?? throw new \RuntimeException(\sprintf('Pas de réponse de %s en %d s', $pAddress, $pTimeoutSeconds));
        } finally {
            $vSocket->close();
        }

        return $rReply;
    }
}
