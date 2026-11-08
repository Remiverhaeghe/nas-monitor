<?php

namespace App\Enum;

enum MonitorType: string
{
    case Heartbeat = 'heartbeat';
    case Http = 'http';
    case Tcp = 'tcp';
    case Tls = 'tls';
    case Dns = 'dns';
    case Icmp = 'icmp';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::Heartbeat => 'Tâche planifiée (ping reçu)',
            self::Http => 'Site web (HTTP)',
            self::Tcp => 'Port TCP',
            self::Tls => 'Certificat TLS',
            self::Dns => 'Résolution DNS',
            self::Icmp => 'Ping ICMP',
        };

        return $rLabel;
    }

    /**
     * Vrai si l'application effectue elle-même le contrôle au lieu d'attendre un ping.
     */
    public function isActive(): bool
    {
        return self::Heartbeat !== $this;
    }
}
