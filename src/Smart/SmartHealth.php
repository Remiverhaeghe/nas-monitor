<?php

namespace App\Smart;

use App\Metric\MetricLimits;

/**
 * Regroupe l'état SMART de plusieurs disques et signale ceux qui montrent les signes d'une panne prochaine.
 */
final class SmartHealth
{
    public const TEMPERATURE = 'disk_temperature_max_c';
    public const FAILED = 'smart_failed_disks';
    public const REALLOCATED = 'smart_reallocated_sectors';
    public const PENDING = 'smart_pending_sectors';
    public const UNCORRECTABLE = 'smart_uncorrectable_sectors';
    public const MEDIA_ERRORS = 'smart_media_errors';
    public const WEAR = 'smart_wear_percent';

    /**
     * Seuils indicatifs appliqués quand le moniteur n'en fixe pas : tout secteur défectueux annonce une usure,
     * et la plupart des fabricants recommandent de rester sous 50 °C en fonctionnement.
     */
    public const DEFAULT_LIMITS = [
        self::TEMPERATURE => 50.0,
        self::FAILED => 0.0,
        self::REALLOCATED => 0.0,
        self::PENDING => 0.0,
        self::UNCORRECTABLE => 0.0,
        self::MEDIA_ERRORS => 0.0,
        self::WEAR => 90.0,
    ];

    /**
     * Pire valeur de chaque métrique sur l'ensemble des disques ; le nombre de disques en échec est une somme.
     *
     * @param array<string, SmartDiskReport> $pReports rapports indexés par nom de disque
     *
     * @return array<string, float>
     */
    public static function metrics(array $pReports): array
    {
        $rMetrics = [];
        foreach ($pReports as $vReport) {
            foreach ($vReport->toMetrics() as $vName => $vValue) {
                $rMetrics[$vName] = self::FAILED === $vName ? ($rMetrics[$vName] ?? 0.0) + $vValue : max($rMetrics[$vName] ?? $vValue, $vValue);
            }
        }

        return $rMetrics;
    }

    /**
     * Dépassements disque par disque, avec les seuils du moniteur à la place des seuils par défaut ;
     * le nombre de disques en échec se compare à son seuil sur l'ensemble des disques.
     *
     * @param array<string, SmartDiskReport> $pReports rapports indexés par nom de disque
     * @param array<string, float>           $pLimits  seuils du moniteur
     *
     * @return list<string>
     */
    public static function findExceeded(array $pReports, array $pLimits): array
    {
        $vLimits = $pLimits + self::DEFAULT_LIMITS;
        $vDiskLimits = array_diff_key($vLimits, [self::FAILED => true]);
        $vFailed = [];
        $rExceeded = [];
        foreach ($pReports as $vDisk => $vReport) {
            if (false === $vReport->healthPassed) {
                $vFailed[] = $vDisk;
            }
            foreach (MetricLimits::findExceeded($vReport->toMetrics(), $vDiskLimits) as $vFinding) {
                $rExceeded[] = $vDisk.' : '.$vFinding;
            }
        }
        if (\count($vFailed) > $vLimits[self::FAILED]) {
            array_unshift($rExceeded, 'test SMART en échec : '.implode(', ', $vFailed));
        }

        return $rExceeded;
    }

    /**
     * Vrai pour les métriques calculées ici, que le relevé ne doit pas fournir lui-même quand il envoie les sorties de smartctl.
     */
    public static function isSmartMetric(string $pName): bool
    {
        $rIsSmart = \array_key_exists($pName, self::DEFAULT_LIMITS);

        return $rIsSmart;
    }
}
