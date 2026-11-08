<?php

namespace App\Controller\Api;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dates au format ISO 8601 en UTC : la conversion d'affichage revient au client.
 */
#[Route('/api/monitors', methods: ['GET'])]
final class MonitorApiController
{
    private const PING_LIMIT = 50;
    private const INCIDENT_LIMIT = 20;

    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly IncidentRepository $aIncidentRepository,
        private readonly PingRepository $aPingRepository,
    ) {
    }

    #[Route('', name: 'api_monitor_list')]
    public function list(): JsonResponse
    {
        $vOpenIncidents = $this->aIncidentRepository->findOpenIndexedByMonitorId();
        $vMonitors = [];
        foreach ($this->aMonitorRepository->findBy([], ['name' => 'ASC']) as $vMonitor) {
            $vData = $this->serializeMonitor($vMonitor);
            $vData['open_incident'] = isset($vOpenIncidents[(int) $vMonitor->getId()])
                ? $this->serializeIncident($vOpenIncidents[(int) $vMonitor->getId()])
                : null;
            $vMonitors[] = $vData;
        }

        $rResponse = new JsonResponse(['monitors' => $vMonitors]);

        return $rResponse;
    }

    #[Route('/{id}', name: 'api_monitor_show', requirements: ['id' => '\d+'])]
    public function show(Monitor $pMonitor): JsonResponse
    {
        $vData = $this->serializeMonitor($pMonitor);
        $vData['incidents'] = array_map(
            $this->serializeIncident(...),
            $this->aIncidentRepository->findLatestForMonitor($pMonitor, self::INCIDENT_LIMIT),
        );
        $vData['pings'] = array_map(
            $this->serializePing(...),
            $this->aPingRepository->findLatestForMonitor($pMonitor, self::PING_LIMIT),
        );

        $rResponse = new JsonResponse($vData);

        return $rResponse;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMonitor(Monitor $pMonitor): array
    {
        $rData = [
            'id' => $pMonitor->getId(),
            'name' => $pMonitor->getName(),
            'type' => $pMonitor->getType()->value,
            'group' => $pMonitor->getGroupName(),
            'parent_id' => $pMonitor->getParent()?->getId(),
            'target' => $pMonitor->getTarget(),
            'status' => $pMonitor->getStatus()->value,
            'paused' => $pMonitor->isPaused(),
            'expected_interval_seconds' => $pMonitor->getExpectedIntervalSeconds(),
            'grace_seconds' => $pMonitor->getGraceSeconds(),
            'last_ping_at' => $pMonitor->getLastPingAt()?->format(\DATE_ATOM),
        ];

        return $rData;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIncident(Incident $pIncident): array
    {
        $rData = [
            'cause' => $pIncident->getCause()->value,
            'started_at' => $pIncident->getStartedAt()->format(\DATE_ATOM),
            'resolved_at' => $pIncident->getResolvedAt()?->format(\DATE_ATOM),
        ];

        return $rData;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePing(Ping $pPing): array
    {
        $rData = [
            'kind' => $pPing->getKind()->value,
            'received_at' => $pPing->getReceivedAt()->format(\DATE_ATOM),
            'duration_ms' => $pPing->getDurationMs(),
            'size_bytes' => $pPing->getSizeBytes(),
            'message' => $pPing->getMessage(),
            'anomaly' => $pPing->getAnomaly(),
            'metrics' => $pPing->getMetrics(),
        ];

        return $rData;
    }
}
