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
use App\Service\AvailabilityCalculator;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly AvailabilityCalculator $aAvailabilityCalculator,
        #[Autowire(param: 'app.display_timezone')]
        private readonly string $aDisplayTimezone,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $vNow = $this->aClock->now();
        $vGroups = $this->aMonitorRepository->findAllGrouped();
        $vMonitors = array_merge(...array_column($vGroups, 'monitors'));

        $vCounts = [];
        foreach (MonitorStatus::cases() as $vStatus) {
            $vCounts[$vStatus->value] = 0;
        }
        foreach ($vMonitors as $vMonitor) {
            ++$vCounts[$vMonitor->getStatus()->value];
        }

        $rResponse = $this->render('dashboard/index.html.twig', [
            'groups' => $vGroups,
            'availability' => $this->aAvailabilityCalculator->compute($vMonitors, $vNow->modify('-7 days'), $vNow),
            'counts' => $vCounts,
            'open_incidents' => $this->aIncidentRepository->findOpenIndexedByMonitorId(),
            'active_maintenances' => array_map(
                fn (MaintenanceWindow $pWindow): array => [
                    'window' => $pWindow,
                    'ends_at' => $pWindow->findOccurrenceStart($vNow, new \DateTimeZone($this->aDisplayTimezone))
                        ?->modify(\sprintf('+%d seconds', $pWindow->getDurationSeconds())),
                ],
                $this->aMaintenanceWindowRepository->findActive($vNow),
            ),
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
            'metric_charts' => $this->aTrendChartFactory->metrics($vSuccesses),
            'pings' => $this->aPingRepository->findLatestForMonitor($pMonitor, self::PING_LIMIT),
            'incidents' => $this->aIncidentRepository->findLatestForMonitor($pMonitor, self::INCIDENT_LIMIT),
        ]);

        return $rResponse;
    }
}
