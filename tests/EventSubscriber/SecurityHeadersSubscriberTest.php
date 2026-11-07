<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityHeadersSubscriberTest extends TestCase
{
    public function testCspContainsRequestNonce(): void
    {
        $vResponse = $this->handle(false, 'https://nas.exemple.fr/');

        $vNonce = $this->aRequest->attributes->get(SecurityHeadersSubscriber::NONCE_ATTRIBUTE);
        self::assertIsString($vNonce);
        self::assertStringContainsString("script-src 'self' 'nonce-".$vNonce."'", (string) $vResponse->headers->get('Content-Security-Policy'));
        self::assertSame('max-age=31536000', $vResponse->headers->get('Strict-Transport-Security'));
    }

    public function testNoHstsOnLocalhost(): void
    {
        $vResponse = $this->handle(false, 'https://localhost:8443/');

        self::assertTrue($vResponse->headers->has('Content-Security-Policy'));
        self::assertFalse($vResponse->headers->has('Strict-Transport-Security'));
    }

    public function testDisabledInDebug(): void
    {
        $vResponse = $this->handle(true, 'https://nas.exemple.fr/');

        self::assertFalse($vResponse->headers->has('Content-Security-Policy'));
    }

    private Request $aRequest;

    private function handle(bool $pDebug, string $pUrl): Response
    {
        $vSubscriber = new SecurityHeadersSubscriber($pDebug);
        $vKernel = $this->createStub(HttpKernelInterface::class);
        $this->aRequest = Request::create($pUrl);
        $rResponse = new Response();

        $vSubscriber->onRequest(new RequestEvent($vKernel, $this->aRequest, HttpKernelInterface::MAIN_REQUEST));
        $vSubscriber->onResponse(new ResponseEvent($vKernel, $this->aRequest, HttpKernelInterface::MAIN_REQUEST, $rResponse));

        return $rResponse;
    }
}
