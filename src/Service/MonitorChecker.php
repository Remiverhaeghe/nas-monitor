<?php

namespace App\Service;

use App\Repository\MonitorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class MonitorChecker
{
    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly IncidentManager $aIncidentManager,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    /**
     * Retourne le nombre de moniteurs dont le statut a changé.
     */
    public function check(): int
    {
        $vNow = $this->aClock->now();
        $vChangedCount = 0;

        foreach ($this->aMonitorRepository->findTimeSensitive() as $vMonitor) {
            $vPreviousStatus = $vMonitor->getStatus();
            if (!$vMonitor->refreshStatus($vNow)) {
                continue;
            }

            ++$vChangedCount;
            $this->aLogger->notice('Moniteur {monitor} : {previous} -> {status}', [
                'monitor' => $vMonitor->getId(),
                'previous' => $vPreviousStatus->value,
                'status' => $vMonitor->getStatus()->value,
            ]);
            $this->aIncidentManager->handleStatusChange($vMonitor, $vNow);
        }

        $this->aEntityManager->flush();
        $rChangedCount = $vChangedCount;

        return $rChangedCount;
    }
}
