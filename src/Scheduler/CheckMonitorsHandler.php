<?php

namespace App\Scheduler;

use App\Check\ActiveCheckRunner;
use App\Service\IncidentReminder;
use App\Service\MonitorChecker;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Les contrôles actifs passent avant la vérification des délais pour qu'un contrôle tout juste dû
 * ne fasse pas passer le moniteur en retard. Les erreurs sont journalisées sans être relancées
 * pour que la minute suivante ait lieu.
 */
#[AsMessageHandler]
final class CheckMonitorsHandler
{
    public function __construct(
        private readonly ActiveCheckRunner $aActiveCheckRunner,
        private readonly MonitorChecker $aMonitorChecker,
        private readonly IncidentReminder $aIncidentReminder,
        private readonly ManagerRegistry $aManagerRegistry,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function __invoke(CheckMonitorsMessage $pMessage): void
    {
        $this->run('contrôles actifs', fn () => $this->aActiveCheckRunner->runDueChecks());
        $this->run('vérification des délais', fn () => $this->aMonitorChecker->check());
        $this->run('rappels', fn () => $this->aIncidentReminder->remind());
    }

    private function run(string $pLabel, callable $pTask): void
    {
        try {
            $pTask();
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de l\'étape {step} : {message}', [
                'step' => $pLabel,
                'message' => $vException->getMessage(),
                'exception' => $vException,
            ]);
            // Après un conflit de version, l'EntityManager est fermé : il faut le recréer
            $this->aManagerRegistry->resetManager();
        }
    }
}
