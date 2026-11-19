<?php

namespace App\Smart;

/**
 * État SMART d'un disque tel que lu dans la sortie de smartctl ; null quand l'information est absente.
 */
final readonly class SmartDiskReport
{
    public function __construct(
        public ?bool $healthPassed = null,
        public ?int $temperatureC = null,
        public ?int $reallocatedSectors = null,
        public ?int $pendingSectors = null,
        public ?int $uncorrectableSectors = null,
        public ?int $mediaErrors = null,
        public ?int $wearPercent = null,
    ) {
    }

    /**
     * Valeurs indexées par nom de métrique, sans les informations absentes.
     *
     * @return array<string, float>
     */
    public function toMetrics(): array
    {
        $vValues = [
            SmartHealth::TEMPERATURE => $this->temperatureC,
            SmartHealth::FAILED => null === $this->healthPassed ? null : ($this->healthPassed ? 0 : 1),
            SmartHealth::REALLOCATED => $this->reallocatedSectors,
            SmartHealth::PENDING => $this->pendingSectors,
            SmartHealth::UNCORRECTABLE => $this->uncorrectableSectors,
            SmartHealth::MEDIA_ERRORS => $this->mediaErrors,
            SmartHealth::WEAR => $this->wearPercent,
        ];
        $rMetrics = array_map(static fn (int $pValue): float => (float) $pValue, array_filter($vValues, static fn (?int $pValue): bool => null !== $pValue));

        return $rMetrics;
    }
}
