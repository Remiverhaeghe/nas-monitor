<?php

namespace App\Dto;

use App\Entity\Monitor;
use Symfony\Component\Validator\Constraints as Assert;

final class MonitorInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 44640)]
    public ?int $intervalMinutes = 60;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 10080)]
    public ?int $graceMinutes = 10;

    public static function fromMonitor(Monitor $pMonitor): self
    {
        $rInput = new self();
        $rInput->name = $pMonitor->getName();
        $rInput->intervalMinutes = intdiv($pMonitor->getExpectedIntervalSeconds(), 60);
        $rInput->graceMinutes = intdiv($pMonitor->getGraceSeconds(), 60);

        return $rInput;
    }
}
