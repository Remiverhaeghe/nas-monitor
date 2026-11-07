<?php

namespace App\Scheduler;

use App\Repository\PingRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgePingsHandler
{
    public function __construct(
        private readonly PingRepository $aPingRepository,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'int:PING_RETENTION_DAYS')]
        private readonly int $aRetentionDays,
    ) {
    }

    public function __invoke(PurgePingsMessage $pMessage): void
    {
        $vLimit = $this->aClock->now()->modify(\sprintf('-%d days', $this->aRetentionDays));

        try {
            $vCount = $this->aPingRepository->deleteReceivedBefore($vLimit);
            $this->aLogger->info('{count} pings de plus de {days} jours supprimés', [
                'count' => $vCount,
                'days' => $this->aRetentionDays,
            ]);
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec de la purge des pings : {message}', ['message' => $vException->getMessage()]);
        }
    }
}
