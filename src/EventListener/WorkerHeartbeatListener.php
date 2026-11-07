<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Met à jour la date d'un fichier à chaque tour de boucle du worker, au plus toutes les 30 secondes.
 * Le contrôle de santé Docker du worker le déclare défaillant si le fichier n'est plus touché.
 */
#[AsEventListener]
final class WorkerHeartbeatListener
{
    private const MIN_INTERVAL_SECONDS = 30;

    private int $aLastBeat = 0;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/worker-heartbeat')]
        private readonly string $aHeartbeatFile,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function __invoke(WorkerRunningEvent $pEvent): void
    {
        $vNow = time();
        if ($vNow - $this->aLastBeat < self::MIN_INTERVAL_SECONDS) {
            return;
        }
        $this->aLastBeat = $vNow;

        if (!@touch($this->aHeartbeatFile)) {
            $this->aLogger->warning('Impossible de mettre à jour {file}', ['file' => $this->aHeartbeatFile]);
        }
    }
}
