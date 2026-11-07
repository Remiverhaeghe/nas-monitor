<?php

namespace App\Service;

use App\Dto\PingPayload;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\PingKind;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class PingRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly IncidentManager $aIncidentManager,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function record(Monitor $pMonitor, PingKind $pKind, ?PingPayload $pPayload, ?string $pSourceIp): Ping
    {
        $vNow = $this->aClock->now();
        $vPreviousStatus = $pMonitor->getStatus();

        $rPing = new Ping(
            $pMonitor,
            $pKind,
            $vNow,
            $pPayload?->durationMs,
            $pPayload?->sizeBytes,
            $pPayload?->message,
            $pSourceIp,
        );
        $vChanged = $pMonitor->recordPing($pKind, $vNow);
        $this->aEntityManager->persist($rPing);

        $this->aLogger->info('Ping {kind} reçu pour le moniteur {monitor}', [
            'kind' => $pKind->value,
            'monitor' => $pMonitor->getId(),
        ]);
        if ($vChanged) {
            $this->aLogger->notice('Moniteur {monitor} : {previous} -> {status}', [
                'monitor' => $pMonitor->getId(),
                'previous' => $vPreviousStatus->value,
                'status' => $pMonitor->getStatus()->value,
            ]);
            $this->aIncidentManager->handleStatusChange($pMonitor, $vNow);
        }

        $this->aEntityManager->flush();

        return $rPing;
    }
}
