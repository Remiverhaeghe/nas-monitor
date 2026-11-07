<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Une erreur HTTP est relancée pour que Messenger réessaie l'envoi.
 */
#[AsMessageHandler]
final class NtfyMessageHandler
{
    public function __construct(
        private readonly HttpClientInterface $aHttpClient,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'NTFY_URL')]
        private readonly string $aUrl,
        #[Autowire(env: 'NTFY_TOKEN')]
        private readonly string $aToken,
    ) {
    }

    public function __invoke(NtfyMessage $pMessage): void
    {
        if ('' === $this->aUrl) {
            $this->aLogger->warning('Notification ntfy ignorée : NTFY_URL n\'est pas configurée');

            return;
        }

        $vHeaders = [
            'Title' => $pMessage->title,
            'Priority' => $pMessage->urgent ? 'high' : 'default',
            'Tags' => $pMessage->urgent ? 'rotating_light' : 'white_check_mark',
        ];
        if ('' !== $this->aToken) {
            $vHeaders['Authorization'] = 'Bearer '.$this->aToken;
        }

        $vResponse = $this->aHttpClient->request('POST', $this->aUrl, [
            'headers' => $vHeaders,
            'body' => $pMessage->body,
            'timeout' => 10,
        ]);
        $vResponse->getHeaders();

        $this->aLogger->info('Notification ntfy envoyée : {title}', ['title' => $pMessage->title]);
    }
}
