<?php

namespace App\Controller;

use App\Chart\TrendChartFactory;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Enum\MonitorStatus;
use App\Repository\IncidentRepository;
use App\Repository\MaintenanceWindowRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use Psr\Clock\ClockInterface;
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
        private readonly TrendChartFactory $aTrendChartFactory,
        private readonly MaintenanceWindowRepository $aMaintenanceWindowRepository,
        private readonly ClockInterface $aClock,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $vNow = $this->aClock->now();
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
            'active_maintenances' => array_values(array_filter(
                $this->aMaintenanceWindowRepository->findNotEnded($vNow),
                static fn (MaintenanceWindow $pWindow): bool => $pWindow->isActive($vNow),
            )),
        ]);

        return $rResponse;
    }

    #[Route('/monitors/{id}', name: 'app_monitor_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Monitor $pMonitor): Response
    {
        $vSuccesses = $this->aPingRepository->findRecentSuccessesForMonitor($pMonitor, self::PING_LIMIT);

        $rResponse = $this->render('monitor/show.html.twig', [
            'monitor' => $pMonitor,
            'duration_chart' => $this->aTrendChartFactory->durations($vSuccesses),
            'size_chart' => $this->aTrendChartFactory->sizes($vSuccesses),
            'pings' => $this->aPingRepository->findLatestForMonitor($pMonitor, self::PING_LIMIT),
            'incidents' => $this->aIncidentRepository->findLatestForMonitor($pMonitor, self::INCIDENT_LIMIT),
        ]);

        return $rResponse;
    }
}
