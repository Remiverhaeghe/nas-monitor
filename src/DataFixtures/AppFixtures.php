<?php

namespace App\DataFixtures;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\IncidentCause;
use App\Enum\MaintenanceRecurrence;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration : un NAS, ses services, ses sauvegardes et un mois d'historique.
 * Compte : demo@exemple.fr, mot de passe « demonstration ».
 */
final class AppFixtures extends Fixture
{
    public const DEMO_EMAIL = 'demo@exemple.fr';
    public const DEMO_PASSWORD = 'demonstration';

    private const DAY = 86400;

    private ObjectManager $aManager;
    private \DateTimeImmutable $aNow;

    public function __construct(
        private readonly UserPasswordHasherInterface $aPasswordHasher,
        private readonly ClockInterface $aClock,
    ) {
    }

    public function load(ObjectManager $pManager): void
    {
        $this->aManager = $pManager;
        $this->aNow = $this->aClock->now();
        mt_srand(2026);

        $vUser = new User(self::DEMO_EMAIL);
        $vUser->setPassword($this->aPasswordHasher->hashPassword($vUser, self::DEMO_PASSWORD));
        $pManager->persist($vUser);

        $vNas = $this->activeMonitor('NAS', MonitorType::Icmp, '192.168.1.10', 'Infrastructure', null, true);
        $vBox = $this->activeMonitor('Box internet', MonitorType::Icmp, '192.168.1.1', 'Infrastructure', null, true);
        $this->activeMonitor('DNS local', MonitorType::Dns, 'nas.exemple.fr=192.168.1.10', 'Infrastructure', null, false);
        $vCloud = $this->activeMonitor('Nextcloud', MonitorType::Http, 'https://cloud.exemple.fr', 'Services', $vNas, true);
        $this->activeMonitor('Jellyfin', MonitorType::Tcp, '192.168.1.10:8096', 'Services', $vNas, true);
        $this->activeMonitor('Certificat cloud.exemple.fr', MonitorType::Tls, 'cloud.exemple.fr:443', 'Services', null, false);

        $this->backupHistory('Sauvegarde restic des photos', 42_000, 1_150);
        $this->backupHistory('Sauvegarde de la base nas-monitor', 18, 4);
        $this->nasReadings($vNas);
        $this->failingSync();

        $this->pastIncident($vCloud, 3 * self::DAY, 25 * 60);
        $this->pastIncident($vBox, 10 * self::DAY, 2 * 3600);

        $vSunday = $this->aNow->modify('last sunday')->setTime(2, 0);
        $pManager->persist(new MaintenanceWindow(null, $vSunday, $vSunday->modify('+2 hours'), 'Mises à jour automatiques du NAS', MaintenanceRecurrence::Weekly));

        $pManager->flush();
    }

    private function activeMonitor(string $pName, MonitorType $pType, string $pTarget, string $pGroup, ?Monitor $pParent, bool $pPublic): Monitor
    {
        $rMonitor = new Monitor($pName, $pType, 300, 120, $this->aNow->modify('-40 days'));
        $rMonitor->setTarget($pTarget);
        $rMonitor->setGroupName($pGroup);
        $rMonitor->setParent($pParent);
        $rMonitor->setPublicVisible($pPublic);
        $this->aManager->persist($rMonitor);
        $this->addPing($rMonitor, PingKind::Success, 60, mt_rand(5, 80), null, 'Contrôle réussi');

        return $rMonitor;
    }

    /**
     * Sauvegarde quotidienne qui grossit lentement, avec une exécution anormale il y a 5 jours.
     */
    private function backupHistory(string $pName, int $pSizeMegabytes, int $pDurationSeconds): void
    {
        $vMonitor = new Monitor($pName, MonitorType::Heartbeat, self::DAY, 3600, $this->aNow->modify('-40 days'));
        $vMonitor->setGroupName('Sauvegardes');
        $vMonitor->rotatePingToken();
        $this->aManager->persist($vMonitor);

        for ($vDay = 30; $vDay >= 1; --$vDay) {
            $vExpectedSize = $pSizeMegabytes * (1 + (30 - $vDay) * 0.004);
            $vSize = 5 === $vDay ? $vExpectedSize * 0.3 : $vExpectedSize;
            $vDuration = $pDurationSeconds * (0.9 + mt_rand(0, 20) / 100);
            $vPing = $this->addPing($vMonitor, PingKind::Success, $vDay * self::DAY - 3 * 3600, (int) ($vDuration * 1000), (int) ($vSize * 1_048_576), 'Snapshot enregistré');
            if (5 === $vDay) {
                $vPing->markAnomaly(\sprintf('Taille de %.1f Mo au lieu de %.1f Mo habituellement', $vSize, $vExpectedSize));
            }
        }
    }

    private function nasReadings(Monitor $pNas): void
    {
        $vMonitor = new Monitor('Relevé du NAS', MonitorType::Heartbeat, 1800, 600, $this->aNow->modify('-40 days'));
        $vMonitor->setGroupName('Infrastructure');
        $vMonitor->setParent($pNas);
        $vMonitor->setMetricLimits(['disk_used_percent' => 85.0, 'temperature_c' => 60.0, 'smart_failed_disks' => 0.0]);
        $vMonitor->rotatePingToken();
        $this->aManager->persist($vMonitor);

        for ($vIndex = 50; $vIndex >= 1; --$vIndex) {
            $vPing = $this->addPing($vMonitor, PingKind::Success, $vIndex * 1800, null, null, 'Relevé de /volume1');
            $vPing->setMetrics([
                'disk_used_percent' => round(78 - $vIndex * 0.04, 1),
                'memory_used_percent' => (float) mt_rand(38, 55),
                'temperature_c' => (float) mt_rand(39, 47),
                'smart_failed_disks' => 0.0,
            ]);
        }
    }

    private function failingSync(): void
    {
        $vMonitor = new Monitor('Synchronisation distante', MonitorType::Heartbeat, 3600, 900, $this->aNow->modify('-40 days'));
        $vMonitor->setGroupName('Sauvegardes');
        $vMonitor->rotatePingToken();
        $this->aManager->persist($vMonitor);

        $this->addPing($vMonitor, PingKind::Success, 4 * 3600, 95_000, null, 'Synchronisation terminée');
        $this->addPing($vMonitor, PingKind::Fail, 2 * 3600, 30_000, null, 'Destination injoignable');
        $vIncident = new Incident($vMonitor, IncidentCause::Failed, $this->aNow->modify('-2 hours'));
        $vIncident->markNotified($this->aNow->modify('-2 hours'));
        $this->aManager->persist($vIncident);
    }

    private function pastIncident(Monitor $pMonitor, int $pSecondsAgo, int $pDurationSeconds): void
    {
        $vIncident = new Incident($pMonitor, IncidentCause::Failed, $this->aNow->modify(\sprintf('-%d seconds', $pSecondsAgo)));
        $vIncident->resolve($this->aNow->modify(\sprintf('-%d seconds', $pSecondsAgo - $pDurationSeconds)));
        $vIncident->markNotified($vIncident->getStartedAt());
        $this->aManager->persist($vIncident);
    }

    private function addPing(Monitor $pMonitor, PingKind $pKind, int $pSecondsAgo, ?int $pDurationMs, ?int $pSizeBytes, string $pMessage): Ping
    {
        $vReceivedAt = $this->aNow->modify(\sprintf('-%d seconds', $pSecondsAgo));
        $rPing = new Ping($pMonitor, $pKind, $vReceivedAt, $pDurationMs, $pSizeBytes, $pMessage);
        $this->aManager->persist($rPing);
        $pMonitor->recordPing($pKind, $vReceivedAt);
        $pMonitor->refreshStatus($this->aNow);

        return $rPing;
    }
}
