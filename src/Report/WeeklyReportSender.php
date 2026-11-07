<?php

namespace App\Report;

use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use App\Service\AvailabilityCalculator;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Bilan des 7 derniers jours par e-mail, envoyé même pendant une maintenance.
 */
final class WeeklyReportSender
{
    private const DATE_FORMAT = 'd/m/Y H:i';

    private \DateTimeZone $aTimezone;

    public function __construct(
        private readonly MonitorRepository $aMonitorRepository,
        private readonly IncidentRepository $aIncidentRepository,
        private readonly PingRepository $aPingRepository,
        private readonly AvailabilityCalculator $aAvailabilityCalculator,
        private readonly MailerInterface $aMailer,
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

    public function send(): void
    {
        $vTo = $this->aClock->now();
        $vFrom = $vTo->modify('-7 days');

        $this->aMailer->send((new Email())
            ->from($this->aFrom)
            ->to($this->aTo)
            ->subject('[nas-monitor] Rapport hebdomadaire')
            ->text($this->buildBody($vFrom, $vTo)));

        $this->aLogger->info('Rapport hebdomadaire envoyé');
    }

    public function buildBody(\DateTimeImmutable $pFrom, \DateTimeImmutable $pTo): string
    {
        $vGroups = $this->aMonitorRepository->findAllGrouped();
        if ([] === $vGroups) {
            return "Aucun moniteur configuré.\n";
        }

        $vAvailability = $this->aAvailabilityCalculator->compute(array_merge(...array_column($vGroups, 'monitors')), $pFrom, $pTo);
        $vAnomalies = $this->aPingRepository->countAnomaliesByMonitorId($pFrom, $pTo);
        $vIncidents = [];
        foreach ($this->aIncidentRepository->findOverlapping($pFrom, $pTo) as $vIncident) {
            if ($vIncident->getStartedAt() >= $pFrom) {
                $vMonitorId = (int) $vIncident->getMonitor()->getId();
                $vIncidents[$vMonitorId] = ($vIncidents[$vMonitorId] ?? 0) + 1;
            }
        }

        $vLines = [\sprintf(
            'Disponibilité du %s au %s, heure de Paris.',
            $pFrom->setTimezone($this->aTimezone)->format(self::DATE_FORMAT),
            $pTo->setTimezone($this->aTimezone)->format(self::DATE_FORMAT),
        )];

        foreach ($vGroups as $vGroup) {
            $vLines[] = '';
            if (\count($vGroups) > 1 || null !== $vGroup['name']) {
                $vLines[] = $vGroup['name'] ?? 'Sans groupe';
            }
            foreach ($vGroup['monitors'] as $vMonitor) {
                $vMonitorId = (int) $vMonitor->getId();
                $vPercent = $vAvailability[$vMonitorId] ?? null;
                $vLines[] = \sprintf(
                    '- %s : %s, %s, %s, actuellement %s',
                    $vMonitor->getName(),
                    null === $vPercent ? 'disponibilité non mesurée' : number_format($vPercent, 1, ',', ' ').' %',
                    $this->plural($vIncidents[$vMonitorId] ?? 0, 'incident'),
                    $this->plural($vAnomalies[$vMonitorId] ?? 0, 'anomalie'),
                    mb_strtolower($vMonitor->getStatus()->label()),
                );
            }
        }

        $rBody = implode("\n", $vLines)."\n";

        return $rBody;
    }

    private function plural(int $pCount, string $pWord): string
    {
        $rText = $pCount.' '.$pWord.($pCount > 1 ? 's' : '');

        return $rText;
    }
}
