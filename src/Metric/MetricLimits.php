<?php

namespace App\Metric;

/**
 * Seuils maximaux des métriques envoyées avec un ping, saisis sous la forme « disk_used_percent=90, temperature_c=60 ».
 */
final class MetricLimits
{
    public const NAME_PATTERN = '/^[a-z0-9_]{1,40}$/';

    /**
     * @return array<string, float>
     *
     * @throws \InvalidArgumentException
     */
    public static function parse(string $pText): array
    {
        $rLimits = [];
        foreach (preg_split('/[,;\n]+/', $pText) ?: [] as $vPart) {
            $vPart = trim($vPart);
            if ('' === $vPart) {
                continue;
            }
            [$vName, $vValue] = array_pad(array_map('trim', explode('=', $vPart, 2)), 2, '');
            if (1 !== preg_match(self::NAME_PATTERN, $vName) || !is_numeric($vValue)) {
                throw new \InvalidArgumentException(\sprintf('Seuil « %s » invalide : nom en minuscules, chiffres ou _, puis =valeur.', $vPart));
            }
            $rLimits[$vName] = (float) $vValue;
        }

        return $rLimits;
    }

    /**
     * @param array<string, float> $pLimits
     */
    public static function format(array $pLimits): string
    {
        $vParts = [];
        foreach ($pLimits as $vName => $vValue) {
            $vParts[] = $vName.'='.self::number($vValue);
        }
        $rText = implode(', ', $vParts);

        return $rText;
    }

    /**
     * @param array<string, float> $pMetrics
     * @param array<string, float> $pLimits
     *
     * @return list<string>
     */
    public static function findExceeded(array $pMetrics, array $pLimits): array
    {
        $rExceeded = [];
        foreach ($pLimits as $vName => $vLimit) {
            if (isset($pMetrics[$vName]) && $pMetrics[$vName] > $vLimit) {
                $rExceeded[] = \sprintf('%s à %s (maximum %s)', $vName, self::number($pMetrics[$vName]), self::number($vLimit));
            }
        }

        return $rExceeded;
    }

    private static function number(float $pValue): string
    {
        $rText = rtrim(rtrim(number_format($pValue, 2, '.', ''), '0'), '.');

        return $rText;
    }
}
