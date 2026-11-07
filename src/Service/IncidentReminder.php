<?php

namespace App\Service;

use App\Notification\AlertNotifier;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signale les pannes qui durent, et celles qui n'ont pas encore été signalées
 * parce qu'elles ont commencé pendant une maintenance.
 */
final class IncidentReminder
{
    public function __construct(
        private readonly IncidentRepository $aIncidentRepository,
        private readonly AlertNotifier $aAlertNotifier,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        #[Autowire(env: 'int:ALERT_REMINDER_HOURS')]
        private readonly int $aReminderHours,
    ) {
    }

    /**
     * Retourne le nombre d'alertes envoyées.
     */
    public function remind(): int
    {
        $vNow = $this->aClock->now();
        $vNotifiedBefore = $this->aReminderHours > 0 ? $vNow->modify(\sprintf('-%d hours', $this->aReminderHours)) : null;
        $vCount = 0;

        foreach ($this->aIncidentRepository->findOpenToNotify($vNotifiedBefore) as $vIncident) {
            $vSent = null === $vIncident->getLastNotifiedAt()
                ? $this->aAlertNotifier->sendDown($vIncident)
                : $this->aAlertNotifier->sendReminder($vIncident, $vNow);
            if (!$vSent) {
                continue;
            }

            $vIncident->markNotified($vNow);
            $this->aLogger->notice('Rappel envoyé pour l\'incident {incident}', ['incident' => $vIncident->getId()]);
            ++$vCount;
        }

        $this->aEntityManager->flush();
        $rCount = $vCount;

        return $rCount;
    }
}
