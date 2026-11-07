<?php

namespace App\Enum;

enum PingKind: string
{
    case Start = 'start';
    case Success = 'success';
    case Fail = 'fail';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::Start => 'Début',
            self::Success => 'Succès',
            self::Fail => 'Échec',
        };

        return $rLabel;
    }
}
