<?php

namespace App\Scheduler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Signale à un service extérieur que le worker et la base fonctionnent : si le NAS tombe,
 * c'est ce service qui alerte.
 */
#[AsMessageHandler]
final class ExternalHeartbeatHandler
{
    public function __construct(
        private readonly HttpClientInterface $aHttpClient,
        private readonly Connection $aConnection,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'HEARTBEAT_URL')]
        private readonly string $aUrl,
    ) {
    }

    public function __invoke(ExternalHeartbeatMessage $pMessage): void
    {
        if ('' === $this->aUrl) {
            return;
        }

        $vUrl = $this->aUrl;
        try {
            $this->aConnection->executeQuery('SELECT 1');
        } catch (\Throwable $vException) {
            $this->aLogger->error('Base de données injoignable : {message}', ['message' => $vException->getMessage()]);
            $vUrl .= '/fail';
        }

        try {
            $this->aHttpClient->request('GET', $vUrl, ['timeout' => 10])->getHeaders();
        } catch (\Throwable $vException) {
            $this->aLogger->error('Signal externe impossible : {message}', ['message' => $vException->getMessage()]);
        }
    }
}
