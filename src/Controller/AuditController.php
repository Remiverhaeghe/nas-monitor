<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditController extends AbstractController
{
    private const LIMIT = 200;

    public function __construct(
        private readonly AuditLogRepository $aAuditLogRepository,
    ) {
    }

    #[Route('/audit', name: 'app_audit_index', methods: ['GET'])]
    public function index(): Response
    {
        $rResponse = $this->render('audit/index.html.twig', [
            'entries' => $this->aAuditLogRepository->findLatest(self::LIMIT),
            'limit' => self::LIMIT,
        ]);

        return $rResponse;
    }
}
