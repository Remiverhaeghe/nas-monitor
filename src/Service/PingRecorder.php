<?php

namespace App\Service;

use App\Dto\PingPayload;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\PingKind;
use App\Metric\MetricLimits;
use App\Notification\AlertNotifier;
use App\Repository\PingRepository;
use App\Smart\SmartDiskReport;
use App\Smart\SmartHealth;
use App\Smart\SmartOutputParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class PingRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly IncidentManager $aIncidentManager,
        private readonly AnomalyDetector $aAnomalyDetector,
        private readonly PingRepository $aPingRepository,
        private readonly AlertNotifier $aAlertNotifier,
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
        $vReports = array_map(SmartOutputParser::parse(...), $pPayload?->normalizedSmart() ?? []);
        $rPing->setMetrics($this->withSmartMetrics($pPayload?->normalizedMetrics(), $vReports));
        if (PingKind::Success === $pKind && !$pMonitor->getType()->isActive()) {
            $this->checkAnomaly($pMonitor, $rPing, $vReports);
        }
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

    /**
     * Quand les sorties de smartctl sont jointes, les métriques SMART en sont tirées et remplacent celles du relevé.
     *
     * @param array<string, float>|null      $pMetrics
     * @param array<string, SmartDiskReport> $pReports
     *
     * @return array<string, float>|null
     */
    private function withSmartMetrics(?array $pMetrics, array $pReports): ?array
    {
        if ([] === $pReports) {
            return $pMetrics;
        }
        $rMetrics = self::withoutSmart($pMetrics ?? []) + SmartHealth::metrics($pReports);

        return $rMetrics;
    }

    /**
     * Anomalie : résultat éloigné des exécutions précédentes, métrique au-delà de son seuil, ou disque qui annonce une panne.
     * L'alerte n'est envoyée qu'au début d'une série d'anomalies, pas à chaque exécution anormale.
     *
     * @param array<string, SmartDiskReport> $pReports
     */
    private function checkAnomaly(Monitor $pMonitor, Ping $pPing, array $pReports): void
    {
        $vHistory = $this->aPingRepository->findRecentSuccessesForMonitor($pMonitor, AnomalyDetector::HISTORY_SIZE);
        $vLimits = $pMonitor->getMetricLimits();
        $vFindings = array_filter([
            $this->aAnomalyDetector->detect($pPing, $vHistory),
            ...MetricLimits::findExceeded($pPing->getMetrics() ?? [], [] === $pReports ? $vLimits : self::withoutSmart($vLimits)),
            ...SmartHealth::findExceeded($pReports, $vLimits),
        ]);
        if ([] === $vFindings) {
            return;
        }
        $vAnomaly = mb_substr(implode(' ; ', $vFindings), 0, 255);

        $pPing->markAnomaly($vAnomaly);
        $this->aLogger->warning('Anomalie sur le moniteur {monitor} : {anomaly}', [
            'monitor' => $pMonitor->getId(),
            'anomaly' => $vAnomaly,
        ]);

        $vPrevious = [] === $vHistory ? null : $vHistory[\count($vHistory) - 1];
        if (null === $vPrevious?->getAnomaly()) {
            $this->aAlertNotifier->sendAnomaly($pMonitor, $vAnomaly, $pPing->getReceivedAt());
        }
    }

    /**
     * @param array<string, float> $pValues
     *
     * @return array<string, float>
     */
    private static function withoutSmart(array $pValues): array
    {
        $rValues = array_filter($pValues, static fn (string $pName): bool => !SmartHealth::isSmartMetric($pName), \ARRAY_FILTER_USE_KEY);

        return $rValues;
    }
}
