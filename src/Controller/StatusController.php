<?php

namespace App\Controller;

use App\Entity\Monitor;
use App\Enum\MonitorStatus;
use App\Repository\MonitorRepository;
use App\Service\AvailabilityCalculator;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page sans connexion : uniquement les moniteurs marqués visibles, sans cible ni message.
 */
final class StatusController extends AbstractController
{
    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly AvailabilityCalculator $aAvailabilityCalculator,
        private readonly ClockInterface $aClock,
    ) {
    }

    #[Route('/status', name: 'app_status', methods: ['GET'])]
    public function index(): Response
    {
        $vNow = $this->aClock->now();
        $vGroups = [];
        foreach ($this->aMonitorRepository->findAllGrouped() as $vGroup) {
            $vVisible = array_values(array_filter($vGroup['monitors'], static fn (Monitor $pMonitor): bool => $pMonitor->isPublicVisible()));
            if ([] !== $vVisible) {
                $vGroups[] = ['name' => $vGroup['name'], 'monitors' => $vVisible];
            }
        }
        $vMonitors = array_merge(...array_column($vGroups, 'monitors'));
        $vDownCount = \count(array_filter($vMonitors, static fn (Monitor $pMonitor): bool => MonitorStatus::Down === $pMonitor->getStatus()));

        $rResponse = $this->render('status/index.html.twig', [
            'groups' => $vGroups,
            'down_count' => $vDownCount,
            'availability' => $this->aAvailabilityCalculator->compute($vMonitors, $vNow->modify('-30 days'), $vNow),
            'now' => $vNow,
        ]);
        $rResponse->setPublic();
        $rResponse->setMaxAge(30);

        return $rResponse;
    }
}
