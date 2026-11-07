<?php

namespace App\Controller;

use App\Dto\PingPayload;
use App\Enum\PingKind;
use App\Repository\MonitorRepository;
use App\Service\PingRecorder;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PingController
{
    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly PingRecorder $aPingRecorder,
        #[Autowire(service: 'limiter.ping')]
        private readonly RateLimiterFactoryInterface $aPingLimiter,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    /**
     * Les noms $token et $kind sont imposés par le routage.
     */
    #[Route(
        '/ping/{token}/{kind}',
        name: 'ping',
        requirements: ['token' => '[0-9a-f]{64}', 'kind' => 'start|success|fail'],
        defaults: ['kind' => 'success'],
        methods: ['POST'],
        stateless: true,
    )]
    public function __invoke(
        Request $pRequest,
        string $token,
        PingKind $kind,
        #[MapRequestPayload(acceptFormat: 'json')] ?PingPayload $pPayload = null,
    ): JsonResponse {
        $vClientIp = $pRequest->getClientIp();

        $vLimit = $this->aPingLimiter->create($vClientIp ?? 'inconnue')->consume();
        if (!$vLimit->isAccepted()) {
            $this->aLogger->warning('Limite de pings atteinte pour {ip}', ['ip' => $vClientIp]);

            return new JsonResponse(
                ['error' => 'too_many_requests'],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => max(1, $vLimit->getRetryAfter()->getTimestamp() - time())],
            );
        }

        $vMonitor = $this->aMonitorRepository->findOneByPingToken($token);
        if (null === $vMonitor || $vMonitor->getType()->isActive()) {
            $this->aLogger->warning('Ping avec un jeton inconnu depuis {ip}', ['ip' => $vClientIp]);

            return new JsonResponse(['error' => 'not_found'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $this->aPingRecorder->record($vMonitor, $kind, $pPayload, $vClientIp);
        } catch (OptimisticLockException) {
            $this->aLogger->notice('Ping en conflit avec une mise à jour du moniteur {monitor}, à réessayer', [
                'monitor' => $vMonitor->getId(),
            ]);

            return new JsonResponse(['error' => 'conflict'], JsonResponse::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => 1]);
        }
        $rResponse = new JsonResponse(['status' => $vMonitor->getStatus()->value]);

        return $rResponse;
    }
}
