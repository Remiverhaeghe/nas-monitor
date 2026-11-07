<?php

namespace App\Service;

use App\Entity\Incident;
use App\Enum\IncidentCause;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class AlertMailer
{
    private const DATE_FORMAT = 'd/m/Y H:i:s \U\T\C';

    public function __construct(
        private readonly MailerInterface $aMailer,
        #[Autowire(env: 'ALERT_EMAIL_FROM')]
        private readonly string $aFrom,
        #[Autowire(env: 'ALERT_EMAIL_TO')]
        private readonly string $aTo,
    ) {
    }

    public function sendDown(Incident $pIncident): void
    {
        $vMonitor = $pIncident->getMonitor();
        $vCause = match ($pIncident->getCause()) {
            IncidentCause::Missed => 'aucun ping reçu dans le délai prévu',
            IncidentCause::Failed => 'la tâche a signalé un échec',
        };

        $this->send(
            'En panne : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » est en panne depuis le %s.\n\nCause : %s.\nDernier ping : %s.\n",
                $vMonitor->getName(),
                $pIncident->getStartedAt()->format(self::DATE_FORMAT),
                $vCause,
                $vMonitor->getLastPingAt()?->format(self::DATE_FORMAT) ?? 'aucun',
            ),
        );
    }

    public function sendRecovered(Incident $pIncident, \DateTimeImmutable $pNow): void
    {
        $vMonitor = $pIncident->getMonitor();

        $this->send(
            'Rétabli : '.$vMonitor->getName(),
            \sprintf(
                "Le moniteur « %s » fonctionne de nouveau depuis le %s.\n\nDurée de la panne : %d min.\n",
                $vMonitor->getName(),
                $pNow->format(self::DATE_FORMAT),
                intdiv($pIncident->getDurationSeconds($pNow), 60),
            ),
        );
    }

    private function send(string $pSubject, string $pBody): void
    {
        $vEmail = (new Email())
            ->from($this->aFrom)
            ->to($this->aTo)
            ->subject('[nas-monitor] '.$pSubject)
            ->text($pBody);

        $this->aMailer->send($vEmail);
    }
}
