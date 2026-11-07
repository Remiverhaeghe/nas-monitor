<?php

namespace App\Service;

use App\Entity\Monitor;
use App\Repository\IncidentRepository;

/**
 * Disponibilité = part de la période passée hors incident. La période commence à la création du moniteur s'il est plus récent.
 */
final class AvailabilityCalculator
{
    public function __construct(
        private readonly IncidentRepository $aIncidentRepository,
    ) {
    }

    /**
     * @param list<Monitor> $pMonitors
     *
     * @return array<int, float|null> pourcentage par identifiant de moniteur, null si le moniteur n'existait pas
     */
    public function compute(array $pMonitors, \DateTimeImmutable $pFrom, \DateTimeImmutable $pTo): array
    {
        $vDowntimes = [];
        foreach ($this->aIncidentRepository->findOverlapping($pFrom, $pTo) as $vIncident) {
            $vMonitorId = (int) $vIncident->getMonitor()->getId();
            $vStart = max($vIncident->getStartedAt(), $pFrom);
            $vEnd = min($vIncident->getResolvedAt() ?? $pTo, $pTo);
            $vDowntimes[$vMonitorId] = ($vDowntimes[$vMonitorId] ?? 0) + max(0, $vEnd->getTimestamp() - $vStart->getTimestamp());
        }

        $rAvailability = [];
        foreach ($pMonitors as $vMonitor) {
            $vMonitorId = (int) $vMonitor->getId();
            $vObserved = $pTo->getTimestamp() - max($vMonitor->getCreatedAt(), $pFrom)->getTimestamp();
            $rAvailability[$vMonitorId] = $vObserved <= 0
                ? null
                : max(0.0, 100.0 * (1 - ($vDowntimes[$vMonitorId] ?? 0) / $vObserved));
        }

        return $rAvailability;
    }
}
