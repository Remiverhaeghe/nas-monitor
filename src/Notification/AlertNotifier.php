<?php

namespace App\Notification;

use App\Entity\Incident;
use App\Enum\IncidentCause;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie les alertes sur le ou les canaux choisis pour le moniteur ; e-mails et ntfy partent par Messenger.
 */
final class AlertNotifier
{
    private const DATE_FORMAT = 'd/m/Y à H:i';

    private \DateTimeZone $aTimezone;

    public function __construct(
        private readonly MailerInterface $aMailer,
        private readonly MessageBusInterface $aMessageBus,
        #[Autowire(env: 'ALERT_EMAIL_FROM')]
        private readonly string $aFrom,
        #[Autowire(env: 'ALERT_EMAIL_TO')]
        private readonly string $aTo,
        #[Autowire(param: 'app.display_timezone')]
        string $pDisplayTimezone,
    ) {
        $this->aTimezone = new \DateTimeZone($pDisplayTimezone);
    }

    public function sendDown(Incident $pIncident): void
    {
        $vMonitor = $pIncident->getMonitor();
        $vCause = match ($pIncident->getCause()) {
            IncidentCause::Missed => 'aucun signal reçu dans le délai prévu',
            IncidentCause::Failed => 'le dernier contrôle a échoué',
        };

        $this->send(
            $pIncident,
            'En panne : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » est en panne depuis le %s.\n\nCause : %s.\nDernier signal : %s.\n",
                $vMonitor->getName(),
                $this->format($pIncident->getStartedAt()),
                $vCause,
                null === $vMonitor->getLastPingAt() ? 'aucun' : $this->format($vMonitor->getLastPingAt()),
            ),
            true,
        );
    }

    public function sendRecovered(Incident $pIncident, \DateTimeImmutable $pNow): void
    {
        $vMonitor = $pIncident->getMonitor();

        $this->send(
            $pIncident,
            'Rétabli : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » fonctionne de nouveau depuis le %s.\n\nDurée de la panne : %d min.\n",
                $vMonitor->getName(),
                $this->format($pNow),
                intdiv($pIncident->getDurationSeconds($pNow), 60),
            ),
            false,
        );
    }

    private function send(Incident $pIncident, string $pSubject, string $pBody, bool $pUrgent): void
    {
        $vChannel = $pIncident->getMonitor()->getNotificationChannel();

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
    }

    private function format(\DateTimeImmutable $pDate): string
    {
        $rFormatted = $pDate->setTimezone($this->aTimezone)->format(self::DATE_FORMAT);

        return $rFormatted;
    }
}
