<?php

namespace App\Service;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Enum\IncidentCause;
use App\Enum\MonitorStatus;
use App\Enum\PingKind;
use App\Notification\AlertNotifier;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ne fait pas de flush : l'appelant enregistre le moniteur et l'incident ensemble.
 */
final class IncidentManager
{
    public function __construct(
        private readonly IncidentRepository $aIncidentRepository,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly AlertNotifier $aAlertNotifier,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function handleStatusChange(Monitor $pMonitor, \DateTimeImmutable $pNow): void
    {
        $vOpenIncident = $this->aIncidentRepository->findOpenForMonitor($pMonitor);
        $vStatus = $pMonitor->getStatus();

        if (MonitorStatus::Down === $vStatus && null === $vOpenIncident) {
            $this->open($pMonitor, $pNow);

            return;
        }
        if (MonitorStatus::Down !== $vStatus && null !== $vOpenIncident) {
            $this->resolve($vOpenIncident, $vStatus, $pNow);
        }
    }

    private function open(Monitor $pMonitor, \DateTimeImmutable $pNow): void
    {
        $vCause = PingKind::Fail === $pMonitor->getLastPingKind() ? IncidentCause::Failed : IncidentCause::Missed;
        $vIncident = new Incident($pMonitor, $vCause, $pNow);
        $this->aEntityManager->persist($vIncident);

        $this->aLogger->warning('Incident ouvert pour le moniteur {monitor} ({cause})', [
            'monitor' => $pMonitor->getId(),
            'cause' => $vCause->value,
        ]);
        if ($this->aAlertNotifier->sendDown($vIncident)) {
            $vIncident->markNotified($pNow);
        }
    }

    private function resolve(Incident $pIncident, MonitorStatus $pStatus, \DateTimeImmutable $pNow): void
    {
        $pIncident->resolve($pNow);

        $this->aLogger->notice('Incident résolu pour le moniteur {monitor} après {duration} s', [
            'monitor' => $pIncident->getMonitor()->getId(),
            'duration' => $pIncident->getDurationSeconds($pNow),
        ]);
        if (MonitorStatus::Up === $pStatus) {
            $this->aAlertNotifier->sendRecovered($pIncident, $pNow);
        }
    }
}
