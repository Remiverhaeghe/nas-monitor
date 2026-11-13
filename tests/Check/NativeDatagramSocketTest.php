<?php

namespace App\Tests\Check;

use App\Check\NativeDatagramSocket;
use PHPUnit\Framework\TestCase;

/**
 * Vrais sockets UDP sur la boucle locale : le protocole 17 marche partout, contrairement à ICMP.
 */
class NativeDatagramSocketTest extends TestCase
{
    private const UDP = 17;

    /**
     * @return array{\Socket, int}
     */
    private function udpServer(): array
    {
        $vServer = socket_create(\AF_INET, \SOCK_DGRAM, self::UDP);
        self::assertInstanceOf(\Socket::class, $vServer);
        socket_bind($vServer, '127.0.0.1', 0);
        socket_getsockname($vServer, $vAddress, $vPort);

        return [$vServer, $vPort];
    }

    public function testUnknownProtocolFailsWithAReadableMessage(): void
    {
        try {
            new NativeDatagramSocket(9999);
            self::fail('Un protocole inconnu aurait dû échouer.');
        } catch (\RuntimeException $vException) {
            self::assertStringStartsWith('Socket ICMP indisponible : ', $vException->getMessage());
            self::assertGreaterThan(\strlen('Socket ICMP indisponible : '), \strlen($vException->getMessage()));
        }
    }

    public function testDatagramsGoThereAndBack(): void
    {
        [$vServer, $vPort] = $this->udpServer();
        $vClient = new NativeDatagramSocket(self::UDP);
        $vClient->setReceiveTimeout(2);

        $vClient->send('bonjour', '127.0.0.1', $vPort);
        socket_recvfrom($vServer, $vReceived, 1500, 0, $vFrom, $vFromPort);
        self::assertSame('bonjour', $vReceived);

        socket_sendto($vServer, 'abcdefghij', 10, 0, $vFrom, $vFromPort);
        self::assertSame('abcd', $vClient->receive(4));

        $vClient->close();
        socket_close($vServer);
    }

    public function testSendToAnInvalidAddressFails(): void
    {
        $vClient = new NativeDatagramSocket(self::UDP);

        try {
            $vClient->send('x', 'pas-une-adresse', 9);
            self::fail('Une adresse invalide aurait dû échouer.');
        } catch (\RuntimeException $vException) {
            self::assertStringStartsWith('Envoi du ping impossible : ', $vException->getMessage());
            self::assertGreaterThan(\strlen('Envoi du ping impossible : '), \strlen($vException->getMessage()));
        } finally {
            $vClient->close();
        }
    }

    public function testReceiveReturnsNullAfterTheTimeout(): void
    {
        $vClient = new NativeDatagramSocket(self::UDP);
        $vClient->setReceiveTimeout(1);

        $vStart = microtime(true);
        $vReply = $vClient->receive(1500);
        $vElapsed = microtime(true) - $vStart;
        $vClient->close();

        self::assertNull($vReply);
        self::assertGreaterThan(0.8, $vElapsed);
        self::assertLessThan(3.0, $vElapsed);
    }
}
