<?php

namespace App\Controller;

use App\Entity\Monitor;
use App\Enum\MonitorStatus;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const PING_LIMIT = 50;
    private const INCIDENT_LIMIT = 20;

    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly IncidentRepository $aIncidentRepository,
        private readonly PingRepository $aPingRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $vMonitors = $this->aMonitorRepository->findAll();
        usort($vMonitors, static fn (Monitor $pLeft, Monitor $pRight): int => [$pLeft->getStatus()->severity(), $pLeft->getName()]
            <=> [$pRight->getStatus()->severity(), $pRight->getName()]);

        $vCounts = [];
        foreach (MonitorStatus::cases() as $vStatus) {
            $vCounts[$vStatus->value] = 0;
        }
        foreach ($vMonitors as $vMonitor) {
            ++$vCounts[$vMonitor->getStatus()->value];
        }

        $rResponse = $this->render('dashboard/index.html.twig', [
            'monitors' => $vMonitors,
            'counts' => $vCounts,
            'open_incidents' => $this->aIncidentRepository->findOpenIndexedByMonitorId(),
        ]);

        return $rResponse;
    }

    #[Route('/monitors/{id}', name: 'app_monitor_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Monitor $pMonitor): Response
    {
        $rResponse = $this->render('monitor/show.html.twig', [
            'monitor' => $pMonitor,
            'pings' => $this->aPingRepository->findLatestForMonitor($pMonitor, self::PING_LIMIT),
            'incidents' => $this->aIncidentRepository->findLatestForMonitor($pMonitor, self::INCIDENT_LIMIT),
        ]);

        return $rResponse;
    }
}
