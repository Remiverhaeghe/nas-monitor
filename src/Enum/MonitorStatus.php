<?php

namespace App\Enum;

enum MonitorStatus: string
{
    case New = 'new';
    case Up = 'up';
    case Late = 'late';
    case Down = 'down';
    case Paused = 'paused';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::New => 'Nouveau',
            self::Up => 'Opérationnel',
            self::Late => 'En retard',
            self::Down => 'En panne',
            self::Paused => 'En pause',
        };

        return $rLabel;
    }

    /**
     * Ordre d'affichage : les moniteurs à traiter en premier.
     */
    public function severity(): int
    {
        $rSeverity = match ($this) {
            self::Down => 0,
            self::Late => 1,
            self::New => 2,
            self::Up => 3,
            self::Paused => 4,
        };

        return $rSeverity;
    }
}
