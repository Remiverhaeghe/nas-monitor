<?php

namespace App\Controller;

use App\Dto\MonitorInput;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use App\Form\MonitorFormType;
use App\Service\IncidentManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Le jeton en clair passe par un message flash : il n'est affiché qu'une fois, sur la page qui suit la redirection.
 */
#[Route('/monitors')]
final class MonitorController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'monitor-action';

    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly IncidentManager $aIncidentManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    #[Route('/new', name: 'app_monitor_new', methods: ['GET', 'POST'])]
    public function new(Request $pRequest): Response
    {
        $vInput = new MonitorInput();
        $vForm = $this->createForm(MonitorFormType::class, $vInput);
        $vForm->handleRequest($pRequest);

        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $vMonitor = new Monitor(
                (string) $vInput->name,
                MonitorType::Heartbeat,
                (int) $vInput->intervalMinutes * 60,
                (int) $vInput->graceMinutes * 60,
                $this->aClock->now(),
            );
            $vToken = $vMonitor->rotatePingToken();
            $this->aEntityManager->persist($vMonitor);
            $this->aEntityManager->flush();

            $this->aLogger->info('Moniteur {monitor} créé', ['monitor' => $vMonitor->getId()]);
            $this->addFlash('ping_token', $vToken);

            return $this->redirectToRoute('app_monitor_show', ['id' => $vMonitor->getId()]);
        }

        $rResponse = $this->render('monitor/form.html.twig', [
            'form' => $vForm,
            'title' => 'Nouveau moniteur',
        ]);

        return $rResponse;
    }

    #[Route('/{id}/edit', name: 'app_monitor_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $pRequest, Monitor $pMonitor): Response
    {
        $vInput = MonitorInput::fromMonitor($pMonitor);
        $vForm = $this->createForm(MonitorFormType::class, $vInput);
        $vForm->handleRequest($pRequest);

        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $vNow = $this->aClock->now();
            $pMonitor->setName((string) $vInput->name);
            $pMonitor->setExpectedIntervalSeconds((int) $vInput->intervalMinutes * 60);
            $pMonitor->setGraceSeconds((int) $vInput->graceMinutes * 60);
            if ($pMonitor->refreshStatus($vNow)) {
                $this->aIncidentManager->handleStatusChange($pMonitor, $vNow);
            }
            $this->aEntityManager->flush();

            $this->aLogger->info('Moniteur {monitor} modifié', ['monitor' => $pMonitor->getId()]);
            $this->addFlash('success', 'Moniteur modifié.');

            return $this->redirectToRoute('app_monitor_show', ['id' => $pMonitor->getId()]);
        }

        $rResponse = $this->render('monitor/form.html.twig', [
            'form' => $vForm,
            'title' => 'Modifier « '.$pMonitor->getName().' »',
            'monitor' => $pMonitor,
        ]);

        return $rResponse;
    }

    #[Route('/{id}/pause', name: 'app_monitor_pause', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
    public function pause(Monitor $pMonitor): Response
    {
        $pMonitor->pause();
        $this->aIncidentManager->handleStatusChange($pMonitor, $this->aClock->now());
        $this->aEntityManager->flush();

        $this->aLogger->info('Moniteur {monitor} mis en pause', ['monitor' => $pMonitor->getId()]);
        $this->addFlash('success', 'Moniteur mis en pause : aucune alerte ne sera envoyée.');

        return $this->redirectToRoute('app_monitor_show', ['id' => $pMonitor->getId()]);
    }

    #[Route('/{id}/resume', name: 'app_monitor_resume', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
    public function resume(Monitor $pMonitor): Response
    {
        $pMonitor->resume();
        $this->aEntityManager->flush();

        $this->aLogger->info('Moniteur {monitor} réactivé', ['monitor' => $pMonitor->getId()]);
        $this->addFlash('success', 'Moniteur réactivé : il attend son prochain ping.');

        return $this->redirectToRoute('app_monitor_show', ['id' => $pMonitor->getId()]);
    }

    #[Route('/{id}/rotate-token', name: 'app_monitor_rotate_token', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
    public function rotateToken(Monitor $pMonitor): Response
    {
        $vToken = $pMonitor->rotatePingToken();
        $this->aEntityManager->flush();

        $this->aLogger->notice('Jeton de ping régénéré pour le moniteur {monitor}', ['monitor' => $pMonitor->getId()]);
        $this->addFlash('ping_token', $vToken);

        return $this->redirectToRoute('app_monitor_show', ['id' => $pMonitor->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_monitor_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
    public function delete(Monitor $pMonitor): Response
    {
        $vMonitorId = $pMonitor->getId();
        $vName = $pMonitor->getName();
        $this->aEntityManager->remove($pMonitor);
        $this->aEntityManager->flush();

        $this->aLogger->notice('Moniteur {monitor} supprimé', ['monitor' => $vMonitorId]);
        $this->addFlash('success', \sprintf('Moniteur « %s » supprimé.', $vName));

        return $this->redirectToRoute('app_dashboard');
    }
}
