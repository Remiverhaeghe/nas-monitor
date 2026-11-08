<?php

namespace App\Notification;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Enum\IncidentCause;
use App\Repository\MaintenanceWindowRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie les alertes sur le ou les canaux choisis pour le moniteur ; e-mails et ntfy partent par Messenger.
 * Chaque méthode retourne faux si l'alerte est retenue : maintenance en cours, ou moniteur parent en panne.
 */
final class AlertNotifier
{
    private const DATE_FORMAT = 'd/m/Y à H:i';

    private \DateTimeZone $aTimezone;

    public function __construct(
        private readonly MailerInterface $aMailer,
        private readonly MessageBusInterface $aMessageBus,
        private readonly MaintenanceWindowRepository $aMaintenanceWindowRepository,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'ALERT_EMAIL_FROM')]
        private readonly string $aFrom,
        #[Autowire(env: 'ALERT_EMAIL_TO')]
        private readonly string $aTo,
        #[Autowire(param: 'app.display_timezone')]
        string $pDisplayTimezone,
    ) {
        $this->aTimezone = new \DateTimeZone($pDisplayTimezone);
    }

    public function sendDown(Incident $pIncident): bool
    {
        $vMonitor = $pIncident->getMonitor();

        $rSent = $this->send(
            $vMonitor,
            'En panne : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » est en panne depuis le %s.\n\nCause : %s.\nDernier signal : %s.\n",
                $vMonitor->getName(),
                $this->format($pIncident->getStartedAt()),
                $this->describeCause($pIncident),
                null === $vMonitor->getLastPingAt() ? 'aucun' : $this->format($vMonitor->getLastPingAt()),
            ),
            true,
        );

        return $rSent;
    }

    public function sendReminder(Incident $pIncident, \DateTimeImmutable $pNow): bool
    {
        $vMonitor = $pIncident->getMonitor();

        $rSent = $this->send(
            $vMonitor,
            'Toujours en panne : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » est toujours en panne, depuis le %s (%d h).\n\nCause : %s.\n",
                $vMonitor->getName(),
                $this->format($pIncident->getStartedAt()),
                intdiv($pIncident->getDurationSeconds($pNow), 3600),
                $this->describeCause($pIncident),
            ),
            true,
        );

        return $rSent;
    }

    public function sendRecovered(Incident $pIncident, \DateTimeImmutable $pNow): bool
    {
        $vMonitor = $pIncident->getMonitor();

        $rSent = $this->send(
            $vMonitor,
            'Rétabli : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » fonctionne de nouveau depuis le %s.\n\nDurée de la panne : %d min.\n",
                $vMonitor->getName(),
                $this->format($pNow),
                intdiv($pIncident->getDurationSeconds($pNow), 60),
            ),
            false,
        );

        return $rSent;
    }

    public function sendAnomaly(Monitor $pMonitor, string $pAnomaly, \DateTimeImmutable $pReceivedAt): bool
    {
        $rSent = $this->send(
            $pMonitor,
            'Anomalie : '.$pMonitor->getName(),
            \sprintf(
                "La tâche « %s » a réussi le %s, mais son résultat est inhabituel.\n\n%s.\n\nÀ vérifier : une sauvegarde qui réussit avec un résultat anormal peut être incomplète.\n",
                $pMonitor->getName(),
                $this->format($pReceivedAt),
                $pAnomaly,
            ),
            true,
        );

        return $rSent;
    }

    private function send(Monitor $pMonitor, string $pSubject, string $pBody, bool $pUrgent): bool
    {
        if ($this->aMaintenanceWindowRepository->isActiveFor($pMonitor, $this->aClock->now())) {
            $this->aLogger->info('Alerte « {subject} » retenue : maintenance en cours', ['subject' => $pSubject]);

            return false;
        }
        if ($pMonitor->isParentDown()) {
            $this->aLogger->info('Alerte « {subject} » retenue : {parent} est en panne', [
                'subject' => $pSubject,
                'parent' => $pMonitor->getParent()?->getName(),
            ]);

            return false;
        }

        $vChannel = $pMonitor->getNotificationChannel();
        if ($vChannel->includesEmail()) {
            $this->aMailer->send((new Email())
                ->from($this->aFrom)
                ->to($this->aTo)
                ->subject('[nas-monitor] '.$pSubject)
                ->text($pBody));
        }
        if ($vChannel->includesNtfy()) {
            $this->aMessageBus->dispatch(new NtfyMessage($pSubject, $pBody, $pUrgent));
        }

        return true;
    }

    private function describeCause(Incident $pIncident): string
    {
        $rCause = match ($pIncident->getCause()) {
            IncidentCause::Missed => 'aucun signal reçu dans le délai prévu',
            IncidentCause::Failed => 'le dernier contrôle a échoué',
        };

        return $rCause;
    }

    private function format(\DateTimeImmutable $pDate): string
    {
        $rFormatted = $pDate->setTimezone($this->aTimezone)->format(self::DATE_FORMAT);

        return $rFormatted;
    }
}
