<?php

namespace App\Dto;

use App\Entity\Monitor;
use App\Enum\MaintenanceRecurrence;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MaintenanceInput
{
    private const MAX_DURATION_DAYS = 30;

    public ?Monitor $monitor = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $startsAt = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $endsAt = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $reason = null;

    #[Assert\NotNull]
    public ?MaintenanceRecurrence $recurrence = MaintenanceRecurrence::None;

    #[Assert\Callback]
    public function validatePeriod(ExecutionContextInterface $pContext): void
    {
        if (null === $this->startsAt || null === $this->endsAt) {
            return;
        }

        if ($this->endsAt <= $this->startsAt) {
            $pContext->buildViolation('La fin doit être postérieure au début.')->atPath('endsAt')->addViolation();
        } elseif ($this->endsAt > $this->startsAt->modify(\sprintf('+%d days', self::MAX_DURATION_DAYS))) {
            $pContext->buildViolation(\sprintf('Une maintenance ne peut pas dépasser %d jours.', self::MAX_DURATION_DAYS))->atPath('endsAt')->addViolation();
        } elseif (null !== ($vPeriodDays = $this->recurrence?->periodDays())
            && $this->endsAt >= $this->startsAt->modify(\sprintf('+%d days', $vPeriodDays))) {
            $pContext->buildViolation('Une maintenance répétée doit être plus courte que l\'intervalle entre deux occurrences.')->atPath('endsAt')->addViolation();
        }
    }
}
