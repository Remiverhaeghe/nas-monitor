<?php

namespace App\Scheduler;

use App\Service\MonitorChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckMonitorsHandler
{
    public function __construct(
        private readonly MonitorChecker $aMonitorChecker,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    /**
     * L'erreur est journalisée sans être relancée pour que la vérification suivante ait lieu.
     */
    public function __invoke(CheckMonitorsMessage $pMessage): void
    {
        try {
            $this->aMonitorChecker->check();
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de la vérification des moniteurs : {message}', [
                'message' => $vException->getMessage(),
                'exception' => $vException,
            ]);
        }
    }
}
