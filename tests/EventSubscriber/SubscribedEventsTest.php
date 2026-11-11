<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LoginAuditSubscriber;
use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SubscribedEventsTest extends TestCase
{
    public function testLoginAuditListensToSuccessAndFailure(): void
    {
        self::assertSame(
            [LoginSuccessEvent::class => 'onLoginSuccess', LoginFailureEvent::class => 'onLoginFailure'],
            LoginAuditSubscriber::getSubscribedEvents(),
        );
    }

    public function testSecurityHeadersListenToRequestAndResponse(): void
    {
        $vEvents = SecurityHeadersSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $vEvents);
        self::assertSame('onResponse', $vEvents[KernelEvents::RESPONSE]);
    }
}
