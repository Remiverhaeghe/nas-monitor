<?php

namespace App\Tests\Scheduler;

use App\Scheduler\ExternalHeartbeatHandler;
use App\Scheduler\ExternalHeartbeatMessage;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExternalHeartbeatHandlerTest extends TestCase
{
    private const URL = 'https://hc-ping.com/abc';

    public function testNothingIsSentWithoutUrl(): void
    {
        $vHttpClient = new MockHttpClient();

        (new ExternalHeartbeatHandler($vHttpClient, $this->createStub(Connection::class), new NullLogger(), ''))(new ExternalHeartbeatMessage());

        self::assertSame(0, $vHttpClient->getRequestsCount());
    }

    public function testHealthyDatabaseSendsSuccess(): void
    {
        $vResponse = new MockResponse();

        (new ExternalHeartbeatHandler(new MockHttpClient($vResponse), $this->createStub(Connection::class), new NullLogger(), self::URL))(new ExternalHeartbeatMessage());

        self::assertSame(self::URL, $vResponse->getRequestUrl());
    }

    public function testUnreachableDatabaseSendsFailure(): void
    {
        $vConnection = $this->createStub(Connection::class);
        $vConnection->method('executeQuery')->willThrowException(new \RuntimeException('connexion refusée'));
        $vResponse = new MockResponse();

        (new ExternalHeartbeatHandler(new MockHttpClient($vResponse), $vConnection, new NullLogger(), self::URL))(new ExternalHeartbeatMessage());

        self::assertSame(self::URL.'/fail', $vResponse->getRequestUrl());
    }

    public function testUnreachableServiceIsLoggedWithoutThrowing(): void
    {
        $vHttpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'connexion refusée']));

        (new ExternalHeartbeatHandler($vHttpClient, $this->createStub(Connection::class), new NullLogger(), self::URL))(new ExternalHeartbeatMessage());

        self::assertSame(1, $vHttpClient->getRequestsCount());
    }
}
