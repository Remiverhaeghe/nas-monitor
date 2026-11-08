<?php

namespace App\Dto;

use App\Metric\MetricLimits;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PingPayload
{
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
