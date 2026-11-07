<?php

namespace App\Tests\Notification;

use App\Notification\NtfyMessage;
use App\Notification\NtfyMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NtfyMessageHandlerTest extends TestCase
{
    public function testUrgentMessageIsPostedWithHeaders(): void
    {
        $vResponse = new MockResponse();
        $vHandler = new NtfyMessageHandler(new MockHttpClient($vResponse), new NullLogger(), 'https://ntfy.sh/nas', 'secret');

        $vHandler(new NtfyMessage('En panne : Sauvegarde', 'Détail', true));

        self::assertSame('POST', $vResponse->getRequestMethod());
        self::assertSame('https://ntfy.sh/nas', $vResponse->getRequestUrl());
        $vHeaders = implode("\n", $vResponse->getRequestOptions()['headers']);
        self::assertStringContainsString('Title: En panne : Sauvegarde', $vHeaders);
        self::assertStringContainsString('Priority: high', $vHeaders);
        self::assertStringContainsString('Authorization: Bearer secret', $vHeaders);
    }

    public function testNothingIsSentWithoutUrl(): void
    {
        $vHttpClient = new MockHttpClient();

        (new NtfyMessageHandler($vHttpClient, new NullLogger(), '', ''))(new NtfyMessage('Titre', 'Corps', false));

        self::assertSame(0, $vHttpClient->getRequestsCount());
    }
}
