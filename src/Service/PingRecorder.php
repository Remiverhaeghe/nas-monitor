<?php

namespace App\Service;

use App\Dto\PingPayload;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\PingKind;
use App\Notification\AlertNotifier;
use App\Repository\PingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class PingRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly IncidentManager $aIncidentManager,
        private readonly AnomalyDetector $aAnomalyDetector,
        private readonly PingRepository $aPingRepository,
        private readonly AlertNotifier $aAlertNotifier,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function record(Monitor $pMonitor, PingKind $pKind, ?PingPayload $pPayload, ?string $pSourceIp): Ping
    {
        $vNow = $this->aClock->now();
        $vPreviousStatus = $pMonitor->getStatus();

        $rPing = new Ping(
            $pMonitor,
            $pKind,
            $vNow,
            $pPayload?->durationMs,
            $pPayload?->sizeBytes,
            $pPayload?->message,
            $pSourceIp,
        );
        if (PingKind::Success === $pKind && !$pMonitor->getType()->isActive()) {
            $this->checkAnomaly($pMonitor, $rPing);
        }
        $vChanged = $pMonitor->recordPing($pKind, $vNow);
        $this->aEntityManager->persist($rPing);

        $this->aLogger->info('Ping {kind} reçu pour le moniteur {monitor}', [
            'kind' => $pKind->value,
            'monitor' => $pMonitor->getId(),
        ]);
        if ($vChanged) {
            $this->aLogger->notice('Moniteur {monitor} : {previous} -> {status}', [
                'monitor' => $pMonitor->getId(),
                'previous' => $vPreviousStatus->value,
                'status' => $pMonitor->getStatus()->value,
            ]);
            $this->aIncidentManager->handleStatusChange($pMonitor, $vNow);
        }

        $this->aEntityManager->flush();

        return $rPing;
    }

    /**
     * L'alerte n'est envoyée qu'au début d'une série d'anomalies, pas à chaque exécution anormale.
     */
    private function checkAnomaly(Monitor $pMonitor, Ping $pPing): void
    {
        $vHistory = $this->aPingRepository->findRecentSuccessesForMonitor($pMonitor, AnomalyDetector::HISTORY_SIZE);
        $vAnomaly = $this->aAnomalyDetector->detect($pPing, $vHistory);
        if (null === $vAnomaly) {
            return;
        }

        $pPing->markAnomaly($vAnomaly);
        $this->aLogger->warning('Anomalie sur le moniteur {monitor} : {anomaly}', [
            'monitor' => $pMonitor->getId(),
            'anomaly' => $vAnomaly,
        ]);

        $vPrevious = [] === $vHistory ? null : $vHistory[\count($vHistory) - 1];
        if (null === $vPrevious?->getAnomaly()) {
            $this->aAlertNotifier->sendAnomaly($pMonitor, $vAnomaly, $pPing->getReceivedAt());
        }
    }
}
