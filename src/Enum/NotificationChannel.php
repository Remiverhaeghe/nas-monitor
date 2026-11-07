<?php

namespace App\Enum;

enum NotificationChannel: string
{
    case Email = 'email';
    case Ntfy = 'ntfy';
    case All = 'all';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::Email => 'E-mail',
            self::Ntfy => 'ntfy (téléphone)',
            self::All => 'E-mail et ntfy',
        };

        return $rLabel;
    }

    public function includesEmail(): bool
    {
        return self::Ntfy !== $this;
    }

    public function includesNtfy(): bool
    {
        return self::Email !== $this;
    }
}
