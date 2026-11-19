<?php

namespace App\Smart;

/**
 * Lit la sortie texte de « smartctl -H -A » pour les disques SATA, NVMe et SAS.
 */
final class SmartOutputParser
{
    /**
     * Identifiants des attributs SATA utiles pour prévoir une panne.
     */
    private const ATTRIBUTE_REALLOCATED = 5;
    private const ATTRIBUTE_AIRFLOW_TEMPERATURE = 190;
    private const ATTRIBUTE_TEMPERATURE = 194;
    private const ATTRIBUTE_PENDING = 197;
    private const ATTRIBUTE_UNCORRECTABLE = 198;

    /**
     * Au-delà, la valeur brute de température regroupe plusieurs octets (minimum, maximum) : seul l'octet bas est la température courante.
     */
    private const MAX_PLAIN_TEMPERATURE = 150;

    public static function parse(string $pOutput): SmartDiskReport
    {
        $vAttributes = self::attributes($pOutput);
        $vTemperature = $vAttributes[self::ATTRIBUTE_TEMPERATURE] ?? $vAttributes[self::ATTRIBUTE_AIRFLOW_TEMPERATURE]
            ?? self::number($pOutput, '/^(?:Temperature|Current Drive Temperature):\s+(\d+)\s*C/mi');
        if (null !== $vTemperature && $vTemperature > self::MAX_PLAIN_TEMPERATURE) {
            $vTemperature &= 0xFF;
        }

        $rReport = new SmartDiskReport(
            healthPassed: self::health($pOutput),
            temperatureC: $vTemperature,
            reallocatedSectors: $vAttributes[self::ATTRIBUTE_REALLOCATED] ?? self::number($pOutput, '/^Elements in grown defect list:\s+(\d+)/mi'),
            pendingSectors: $vAttributes[self::ATTRIBUTE_PENDING] ?? null,
            uncorrectableSectors: $vAttributes[self::ATTRIBUTE_UNCORRECTABLE] ?? null,
            mediaErrors: self::number($pOutput, '/^Media and Data Integrity Errors:\s+(\d+)/mi'),
            wearPercent: self::number($pOutput, '/^Percentage Used:\s+(\d+)%/mi'),
        );

        return $rReport;
    }

    /**
     * Un avertissement critique NVMe (réserve épuisée, surchauffe, lecture seule) compte comme un échec.
     */
    private static function health(string $pOutput): ?bool
    {
        $vCriticalWarning = 1 === preg_match('/^Critical Warning:\s+0x([0-9a-f]+)/mi', $pOutput, $vMatch) ? hexdec($vMatch[1]) : 0;
        $rPassed = null;
        if (1 === preg_match('/^SMART overall-health self-assessment test result:\s+(\S+)/mi', $pOutput, $vMatch)) {
            $rPassed = 'PASSED' === $vMatch[1];
        } elseif (1 === preg_match('/^SMART Health Status:\s+(\S+)/mi', $pOutput, $vMatch)) {
            $rPassed = 'OK' === $vMatch[1];
        }
        if (null !== $rPassed && $vCriticalWarning > 0) {
            $rPassed = false;
        }

        return $rPassed;
    }

    /**
     * Valeur brute (dixième colonne) de chaque ligne du tableau des attributs SATA.
     *
     * @return array<int, int>
     */
    private static function attributes(string $pOutput): array
    {
        preg_match_all('/^\s*(\d{1,3})\s+\S+\s+0x[0-9a-f]{4}(?:\s+\S+){6}\s+(\d+)/mi', $pOutput, $vMatches, \PREG_SET_ORDER);
        $rAttributes = [];
        foreach ($vMatches as $vMatch) {
            $rAttributes[intval($vMatch[1])] = intval($vMatch[2]);
        }

        return $rAttributes;
    }

    private static function number(string $pOutput, string $pPattern): ?int
    {
        $rNumber = 1 === preg_match($pPattern, $pOutput, $vMatch) ? intval($vMatch[1]) : null;

        return $rNumber;
    }
}
