<?php

namespace App\Tests\Check;

use App\Check\DatagramSocket;
use App\Check\DatagramSocketFactory;
use App\Check\SocketPingTransport;
use PHPUnit\Framework\TestCase;

class SocketPingTransportTest extends TestCase
{
    /**
     * @return array{SocketPingTransport, object{calls: list<string>}}
     */
    private function transport(?string $pReply, ?\RuntimeException $pSendError = null): array
    {
        $vSocket = new class($pReply, $pSendError) implements DatagramSocket {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly ?string $aReply, private readonly ?\RuntimeException $aSendError)
            {
            }

            public function setReceiveTimeout(int $pSeconds): void
            {
                $this->calls[] = 'timeout:'.$pSeconds;
            }

            public function send(string $pPacket, string $pAddress, int $pPort): void
            {
                $this->calls[] = \sprintf('send:%s:%s:%d', $pPacket, $pAddress, $pPort);
                if (null !== $this->aSendError) {
                    throw $this->aSendError;
                }
            }

            public function receive(int $pLength): ?string
            {
                $this->calls[] = 'receive:'.$pLength;

                return $this->aReply;
            }

            public function close(): void
            {
                $this->calls[] = 'close';
            }
        };
        $vFactory = new class($vSocket) implements DatagramSocketFactory {
            public function __construct(private readonly DatagramSocket $aSocket)
            {
            }

            public function open(): DatagramSocket
            {
                return $this->aSocket;
            }
        };

        return [new SocketPingTransport($vFactory), $vSocket];
    }

    public function testEchoSendsOnPortZeroAndReturnsTheReply(): void
    {
        [$vTransport, $vSocket] = $this->transport("\x00\x00");

        self::assertSame("\x00\x00", $vTransport->echo('10.0.0.1', 'paquet', 3));
        self::assertSame(['timeout:3', 'send:paquet:10.0.0.1:0', 'receive:1500', 'close'], $vSocket->calls);
    }

    public function testSilenceIsAnErrorAndTheSocketIsClosed(): void
    {
        [$vTransport, $vSocket] = $this->transport(null);

        try {
            $vTransport->echo('10.0.0.1', 'paquet', 3);
            self::fail('Le silence aurait dû lever une exception.');
        } catch (\RuntimeException $vException) {
            self::assertSame('Pas de réponse de 10.0.0.1 en 3 s', $vException->getMessage());
            self::assertSame('close', end($vSocket->calls));
        }
    }

    public function testSendFailureClosesTheSocketToo(): void
    {
        [$vTransport, $vSocket] = $this->transport('x', new \RuntimeException('Envoi du ping impossible : test'));

        try {
            $vTransport->echo('10.0.0.1', 'paquet', 3);
            self::fail('L\'échec d\'envoi aurait dû être propagé.');
        } catch (\RuntimeException $vException) {
            self::assertSame('Envoi du ping impossible : test', $vException->getMessage());
            self::assertSame('close', end($vSocket->calls));
            self::assertNotContains('receive:1500', $vSocket->calls);
        }
    }
}
