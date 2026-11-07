<?php

namespace App\Audit;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Trace les actions sensibles ; un échec d'écriture est journalisé sans interrompre l'action.
 */
final class AuditLogger
{
    public const CONSOLE_ACTOR = 'console';
    public const ANONYMOUS_ACTOR = 'anonyme';

    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly Security $aSecurity,
        private readonly RequestStack $aRequestStack,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function log(AuditAction $pAction, string $pDetail, ?string $pActor = null): void
    {
        $vRequest = $this->aRequestStack->getMainRequest();
        $vActor = $pActor
            ?? $this->aSecurity->getUser()?->getUserIdentifier()
            ?? (null === $vRequest ? self::CONSOLE_ACTOR : self::ANONYMOUS_ACTOR);
        $vIpAddress = $vRequest?->getClientIp();

        try {
            $this->aEntityManager->persist(new AuditLog($this->aClock->now(), $vActor, $pAction, $pDetail, $vIpAddress));
            $this->aEntityManager->flush();
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de l\'écriture du journal d\'audit ({action}) : {message}', [
                'action' => $pAction->value,
                'message' => $vException->getMessage(),
            ]);
        }
    }
}
