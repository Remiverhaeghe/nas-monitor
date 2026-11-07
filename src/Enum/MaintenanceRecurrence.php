<?php

namespace App\Enum;

enum MaintenanceRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::None => 'Une seule fois',
            self::Daily => 'Chaque jour',
            self::Weekly => 'Chaque semaine',
        };

        return $rLabel;
    }

    public function periodDays(): ?int
    {
        $rDays = match ($this) {
            self::None => null,
            self::Daily => 1,
            self::Weekly => 7,
        };

        return $rDays;
    }
}
