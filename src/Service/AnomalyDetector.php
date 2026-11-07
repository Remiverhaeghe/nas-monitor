<?php

namespace App\Service;

use App\Entity\Ping;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compare un succès à la médiane des succès précédents : une sauvegarde qui réussit
 * mais ne sauvegarde presque plus rien ne déclenche aucune autre alerte.
 */
final class AnomalyDetector
{
    public const HISTORY_SIZE = 10;
    private const MIN_HISTORY = 5;

    public function __construct(
        #[Autowire(env: 'int:ANOMALY_SIZE_DROP_PERCENT')]
        private readonly int $aSizeDropPercent,
        #[Autowire(env: 'int:ANOMALY_DURATION_FACTOR')]
        private readonly int $aDurationFactor,
    ) {
    }

    /**
     * Retourne la description de l'anomalie, ou null si le ping est dans la norme.
     *
     * @param list<Ping> $pHistory succès précédents du même moniteur
     */
    public function detect(Ping $pPing, array $pHistory): ?string
    {
        $vAnomalies = [];

        $vSizeMedian = self::median(array_map(static fn (Ping $pItem): ?int => $pItem->getSizeBytes(), $pHistory));
        $vSize = $pPing->getSizeBytes();
        if (null !== $vSize && null !== $vSizeMedian && $vSizeMedian > 0
            && $vSize < $vSizeMedian * (100 - $this->aSizeDropPercent) / 100) {
            $vAnomalies[] = \sprintf('taille de %s au lieu de %s habituellement', self::formatSize($vSize), self::formatSize((int) $vSizeMedian));
        }

        $vDurationMedian = self::median(array_map(static fn (Ping $pItem): ?int => $pItem->getDurationMs(), $pHistory));
        $vDuration = $pPing->getDurationMs();
        if (null !== $vDuration && null !== $vDurationMedian && $vDurationMedian > 0
            && $vDuration > $vDurationMedian * $this->aDurationFactor) {
            $vAnomalies[] = \sprintf('durée de %.1f s au lieu de %.1f s habituellement', $vDuration / 1000, $vDurationMedian / 1000);
        }

        $rAnomaly = [] === $vAnomalies ? null : ucfirst(implode(', ', $vAnomalies));

        return $rAnomaly;
    }

    /**
     * @param list<?int> $pValues
     */
    private static function median(array $pValues): ?float
    {
        $vValues = array_values(array_filter($pValues, static fn (?int $pValue): bool => null !== $pValue));
        $vCount = \count($vValues);
        if ($vCount < self::MIN_HISTORY) {
            return null;
        }

        sort($vValues);
        $vMiddle = intdiv($vCount, 2);
        $rMedian = 0 === $vCount % 2 ? ($vValues[$vMiddle - 1] + $vValues[$vMiddle]) / 2 : (float) $vValues[$vMiddle];

        return $rMedian;
    }

    private static function formatSize(int $pBytes): string
    {
        $rFormatted = $pBytes >= 1048576
            ? \sprintf('%.1f Mo', $pBytes / 1048576)
            : \sprintf('%.1f Ko', $pBytes / 1024);

        return $rFormatted;
    }
}
