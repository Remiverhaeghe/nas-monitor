<?php

namespace App\Enum;

enum IncidentCause: string
{
    case Missed = 'missed';
    case Failed = 'failed';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::Missed => 'Ping manquant',
            self::Failed => 'Échec signalé',
        };

        return $rLabel;
    }
}
