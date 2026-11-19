<?php

namespace App\Dto;

use App\Metric\MetricLimits;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PingPayload
{
    public const SMART_MAX_DISKS = 16;
    public const SMART_MAX_LENGTH = 20000;
    private const DISK_NAME_PATTERN = '/^[a-z0-9]{1,16}$/';

    #[SerializedName('duration_ms')]
    #[Assert\PositiveOrZero]
    public ?int $durationMs = null;

    #[SerializedName('size_bytes')]
    #[Assert\PositiveOrZero]
    public ?int $sizeBytes = null;

    public ?string $message = null;

    /**
     * Mesures libres, par exemple {"disk_used_percent": 82, "temperature_c": 45}, vérifiées par validateMetrics().
     *
     * @var array<mixed>|null
     */
    #[Assert\Count(max: 20)]
    public ?array $metrics = null;

    /**
     * Sorties texte de « smartctl -H -A » indexées par nom de disque, par exemple {"sda": "..."}, vérifiées par validateSmart().
     *
     * @var array<mixed>|null
     */
    #[Assert\Count(max: self::SMART_MAX_DISKS)]
    public ?array $smart = null;

    #[Assert\Callback]
    public function validateSmart(ExecutionContextInterface $pContext): void
    {
        foreach ($this->smart ?? [] as $vDisk => $vOutput) {
            if (!\is_string($vDisk) || 1 !== preg_match(self::DISK_NAME_PATTERN, $vDisk) || !\is_string($vOutput) || \strlen($vOutput) > self::SMART_MAX_LENGTH) {
                $pContext->buildViolation('Relevé SMART invalide : nom de disque en minuscules ou chiffres, sortie texte de 20 000 caractères au plus.')->atPath('smart')->addViolation();

                return;
            }
        }
    }

    /**
     * À appeler après validation.
     *
     * @return array<string, string>
     */
    public function normalizedSmart(): array
    {
        $rSmart = [];
        foreach ($this->smart ?? [] as $vDisk => $vOutput) {
            if (\is_string($vDisk) && \is_string($vOutput)) {
                $rSmart[$vDisk] = $vOutput;
            }
        }

        return $rSmart;
    }

    #[Assert\Callback]
    public function validateMetrics(ExecutionContextInterface $pContext): void
    {
        foreach ($this->metrics ?? [] as $vName => $vValue) {
            if (!\is_string($vName) || 1 !== preg_match(MetricLimits::NAME_PATTERN, $vName) || !\is_int($vValue) && !\is_float($vValue)) {
                $pContext->buildViolation('Métrique invalide : nom en minuscules, chiffres ou _, valeur numérique.')->atPath('metrics')->addViolation();

                return;
            }
        }
    }

    /**
     * À appeler après validation.
     *
     * @return array<string, float>
     */
    public function normalizedMetrics(): array
    {
        $rMetrics = [];
        foreach ($this->metrics ?? [] as $vName => $vValue) {
            if (\is_string($vName) && (\is_int($vValue) || \is_float($vValue))) {
                $rMetrics[$vName] = (float) $vValue;
            }
        }

        return $rMetrics;
    }
}
