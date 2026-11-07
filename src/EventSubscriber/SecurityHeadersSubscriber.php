<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Désactivé en mode debug : la barre de débogage et le rechargement à chaud injectent leurs propres scripts.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public const NONCE_ATTRIBUTE = 'csp_nonce';

    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function __construct(
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $aDebug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 512],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $pEvent): void
    {
        if ($pEvent->isMainRequest()) {
            $pEvent->getRequest()->attributes->set(self::NONCE_ATTRIBUTE, base64_encode(random_bytes(16)));
        }
    }

    public function onResponse(ResponseEvent $pEvent): void
    {
        if ($this->aDebug || !$pEvent->isMainRequest()) {
            return;
        }

        $vRequest = $pEvent->getRequest();
        $vHeaders = $pEvent->getResponse()->headers;
        $vNonce = (string) $vRequest->attributes->get(self::NONCE_ATTRIBUTE);

        $vHeaders->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$vNonce."'",
            "style-src 'self' 'nonce-".$vNonce."'",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        if ($vRequest->isSecure() && !\in_array($vRequest->getHost(), self::LOCAL_HOSTS, true)) {
            $vHeaders->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
