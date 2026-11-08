<?php

namespace App\Enum;

enum AuditAction: string
{
    case Login = 'login';
    case LoginFailure = 'login_failure';
    case MonitorCreate = 'monitor_create';
    case MonitorEdit = 'monitor_edit';
    case MonitorPause = 'monitor_pause';
    case MonitorResume = 'monitor_resume';
    case MonitorTokenRotate = 'monitor_token_rotate';
    case MonitorDelete = 'monitor_delete';
    case MaintenanceCreate = 'maintenance_create';
    case MaintenanceDelete = 'maintenance_delete';
    case UserCreate = 'user_create';
    case ApiTokenCreate = 'api_token_create';
    case TotpEnable = 'totp_enable';
    case TotpDisable = 'totp_disable';
    case BackupCodesGenerate = 'backup_codes_generate';
    case TotpKeyRotate = 'totp_key_rotate';

    public function label(): string
    {
        $rLabel = match ($this) {
            self::Login => 'Connexion',
            self::LoginFailure => 'Échec de connexion',
            self::MonitorCreate => 'Création de moniteur',
            self::MonitorEdit => 'Modification de moniteur',
            self::MonitorPause => 'Mise en pause',
            self::MonitorResume => 'Reprise',
            self::MonitorTokenRotate => 'Régénération du jeton',
            self::MonitorDelete => 'Suppression de moniteur',
            self::MaintenanceCreate => 'Maintenance planifiée',
            self::MaintenanceDelete => 'Maintenance supprimée',
            self::UserCreate => 'Création de compte',
            self::ApiTokenCreate => 'Création de jeton d\'API',
            self::TotpEnable => 'Double authentification activée',
            self::TotpDisable => 'Double authentification désactivée',
            self::BackupCodesGenerate => 'Nouveaux codes de secours',
            self::TotpKeyRotate => 'Rotation de la clé TOTP',
        };

        return $rLabel;
    }
}
