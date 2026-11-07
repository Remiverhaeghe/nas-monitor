<?php

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Dto\MaintenanceInput;
use App\Entity\MaintenanceWindow;
use App\Enum\AuditAction;
use App\Enum\MaintenanceRecurrence;
use App\Form\MaintenanceFormType;
use App\Repository\MaintenanceWindowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/maintenances')]
final class MaintenanceController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'maintenance-action';

    public function __construct(
        private readonly MaintenanceWindowRepository $aMaintenanceWindowRepository,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        private readonly AuditLogger $aAuditLogger,
        #[Autowire(param: 'app.display_timezone')]
        private readonly string $aDisplayTimezone,
    ) {
    }

    #[Route('', name: 'app_maintenance_index', methods: ['GET', 'POST'])]
    public function index(Request $pRequest): Response
    {
        $vNow = $this->aClock->now();
        $vInput = new MaintenanceInput();
        $vInput->startsAt = $vNow;
        $vInput->endsAt = $vNow->modify('+1 hour');
        $vForm = $this->createForm(MaintenanceFormType::class, $vInput, ['display_timezone' => $this->aDisplayTimezone]);
        $vForm->handleRequest($pRequest);

        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $vWindow = new MaintenanceWindow(
                $vInput->monitor,
                $vInput->startsAt ?? $vNow,
                $vInput->endsAt ?? $vNow,
                (string) $vInput->reason,
                $vInput->recurrence ?? MaintenanceRecurrence::None,
            );
            $this->aEntityManager->persist($vWindow);
            $this->aEntityManager->flush();

            $this->aLogger->info('Maintenance {window} planifiée', ['window' => $vWindow->getId()]);
            $this->aAuditLogger->log(AuditAction::MaintenanceCreate, $this->describe($vWindow));
            $this->addFlash('success', 'Maintenance planifiée.');

            return $this->redirectToRoute('app_maintenance_index');
        }

        $rResponse = $this->render('maintenance/index.html.twig', [
            'form' => $vForm,
            'rows' => array_map(
                fn (MaintenanceWindow $pWindow): array => [
                    'window' => $pWindow,
                    'schedule' => $pWindow->describeSchedule($this->timezone()),
                    'active' => $pWindow->isActive($vNow, $this->timezone()),
                    'next_start' => $pWindow->findOccurrenceStart($vNow, $this->timezone()),
                ],
                $this->aMaintenanceWindowRepository->findNotEnded($vNow),
            ),
        ]);

        return $rResponse;
    }

    #[Route('/{id}/delete', name: 'app_maintenance_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
    public function delete(MaintenanceWindow $pWindow): Response
    {
        $vWindowId = $pWindow->getId();
        $vDescription = $this->describe($pWindow);
        $this->aEntityManager->remove($pWindow);
        $this->aEntityManager->flush();

        $this->aLogger->info('Maintenance {window} supprimée', ['window' => $vWindowId]);
        $this->aAuditLogger->log(AuditAction::MaintenanceDelete, $vDescription);
        $this->addFlash('success', 'Maintenance supprimée : les alertes reprennent.');

        return $this->redirectToRoute('app_maintenance_index');
    }

    private function describe(MaintenanceWindow $pWindow): string
    {
        $rDescription = \sprintf(
            '%s, %s : %s',
            $pWindow->getMonitor()?->getName() ?? 'tous les moniteurs',
            $pWindow->describeSchedule($this->timezone()),
            $pWindow->getReason(),
        );

        return $rDescription;
    }

    private function timezone(): \DateTimeZone
    {
        $rTimezone = new \DateTimeZone($this->aDisplayTimezone);

        return $rTimezone;
    }
}
