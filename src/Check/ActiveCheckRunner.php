<?php

namespace App\Check;

use App\Dto\PingPayload;
use App\Enum\PingKind;
use App\Repository\MonitorRepository;
use App\Service\PingRecorder;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class ActiveCheckRunner
{
    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly ActiveChecker $aActiveChecker,
        private readonly PingRecorder $aPingRecorder,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    /**
     * Retourne le nombre de contrôles effectués.
     */
    public function runDueChecks(): int
    {
        $vNow = $this->aClock->now();
        $vCount = 0;

        foreach ($this->aMonitorRepository->findActiveChecks() as $vMonitor) {
            if (!$vMonitor->isCheckDue($vNow)) {
                continue;
            }

            $vResult = $this->aActiveChecker->check($vMonitor);
            $vPayload = new PingPayload();
            $vPayload->durationMs = $vResult->durationMs;
            $vPayload->message = $vResult->message;

            $this->aPingRecorder->record($vMonitor, $vResult->success ? PingKind::Success : PingKind::Fail, $vPayload, null);
            if (!$vResult->success) {
                $this->aLogger->warning('Contrôle en échec pour le moniteur {monitor} : {message}', [
                    'monitor' => $vMonitor->getId(),
                    'message' => $vResult->message,
                ]);
            }
            ++$vCount;
        }

        $rCount = $vCount;

        return $rCount;
    }
}
