<?php

namespace App\Scheduler;

use App\Repository\AuditLogRepository;
use App\Repository\PingRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeHistoryHandler
{
    public function __construct(
        private readonly PingRepository $aPingRepository,
        private readonly AuditLogRepository $aAuditLogRepository,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'int:PING_RETENTION_DAYS')]
        private readonly int $aPingRetentionDays,
        #[Autowire(env: 'int:AUDIT_RETENTION_DAYS')]
        private readonly int $aAuditRetentionDays,
    ) {
    }

    public function __invoke(PurgeHistoryMessage $pMessage): void
    {
        $vNow = $this->aClock->now();

        try {
            $vCount = $this->aPingRepository->deleteReceivedBefore($vNow->modify(\sprintf('-%d days', $this->aPingRetentionDays)));
            $this->aLogger->info('{count} pings de plus de {days} jours supprimés', [
                'count' => $vCount,
                'days' => $this->aPingRetentionDays,
            ]);
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de la purge des pings : {message}', ['message' => $vException->getMessage()]);
        }

        try {
            $vCount = $this->aAuditLogRepository->deleteOccurredBefore($vNow->modify(\sprintf('-%d days', $this->aAuditRetentionDays)));
            $this->aLogger->info('{count} entrées du journal de plus de {days} jours supprimées', [
                'count' => $vCount,
                'days' => $this->aAuditRetentionDays,
            ]);
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de la purge du journal : {message}', ['message' => $vException->getMessage()]);
        }
    }
}
