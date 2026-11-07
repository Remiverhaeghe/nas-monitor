<?php

namespace App\Entity;

use App\Enum\MaintenanceRecurrence;
use App\Repository\MaintenanceWindowRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plage pendant laquelle aucune alerte n'est envoyée ; sans moniteur, elle s'applique à tous.
 * Une plage récurrente se répète sans fin à partir de la première occurrence (startsAt, endsAt).
 */
#[ORM\Entity(repositoryClass: MaintenanceWindowRepository::class)]
#[ORM\Index(name: 'idx_maintenance_period', columns: ['starts_at', 'ends_at'])]
class MaintenanceWindow
{
    private const DATE_FORMAT = 'd/m/Y H:i';
    private const DAY_NAMES = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Monitor $monitor;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(length: 255)]
    private string $reason;

    #[ORM\Column(length: 16, enumType: MaintenanceRecurrence::class, options: ['default' => 'none'])]
    private MaintenanceRecurrence $recurrence;

    public function __construct(
        ?Monitor $pMonitor,
        \DateTimeImmutable $pStartsAt,
        \DateTimeImmutable $pEndsAt,
        string $pReason,
        MaintenanceRecurrence $pRecurrence = MaintenanceRecurrence::None,
    ) {
        $this->monitor = $pMonitor;
        $this->startsAt = $pStartsAt;
        $this->endsAt = $pEndsAt;
        $this->reason = $pReason;
        $this->recurrence = $pRecurrence;
    }

    public function isActive(\DateTimeImmutable $pNow, \DateTimeZone $pTimezone): bool
    {
        $vStart = $this->findOccurrenceStart($pNow, $pTimezone);
        $rActive = null !== $vStart
            && $vStart <= $pNow
            && $pNow->getTimestamp() < $vStart->getTimestamp() + $this->getDurationSeconds();

        return $rActive;
    }

    /**
     * Début de l'occurrence en cours ou de la prochaine, null si la plage est terminée.
     * Les occurrences gardent l'heure locale de la première, y compris après un changement d'heure.
     */
    public function findOccurrenceStart(\DateTimeImmutable $pNow, \DateTimeZone $pTimezone): ?\DateTimeImmutable
    {
        $vPeriodDays = $this->recurrence->periodDays();
        if (null === $vPeriodDays || $pNow < $this->endsAt) {
            return $pNow < $this->endsAt ? $this->startsAt : null;
        }

        $vFirst = $this->startsAt->setTimezone($pTimezone);
        $vElapsedDays = (int) (new \DateTimeImmutable($vFirst->format('Y-m-d')))
            ->diff(new \DateTimeImmutable($pNow->setTimezone($pTimezone)->format('Y-m-d')))
            ->days;
        $vCandidate = $vFirst->modify(\sprintf('+%d days', $vElapsedDays - $vElapsedDays % $vPeriodDays));
        $vPrevious = $vCandidate->modify(\sprintf('-%d days', $vPeriodDays));
        $vDuration = $this->getDurationSeconds();

        if ($vCandidate > $pNow && $vPrevious >= $vFirst && $vPrevious->getTimestamp() + $vDuration > $pNow->getTimestamp()) {
            $vCandidate = $vPrevious;
        } elseif ($vCandidate->getTimestamp() + $vDuration <= $pNow->getTimestamp()) {
            $vCandidate = $vCandidate->modify(\sprintf('+%d days', $vPeriodDays));
        }
        $rStart = $vCandidate->setTimezone(new \DateTimeZone('UTC'));

        return $rStart;
    }

    public function describeSchedule(\DateTimeZone $pTimezone): string
    {
        $vStart = $this->startsAt->setTimezone($pTimezone);
        $vEnd = $this->endsAt->setTimezone($pTimezone);

        $rSchedule = match ($this->recurrence) {
            MaintenanceRecurrence::None => \sprintf('du %s au %s', $vStart->format(self::DATE_FORMAT), $vEnd->format(self::DATE_FORMAT)),
            MaintenanceRecurrence::Daily => \sprintf('chaque jour de %s à %s, à partir du %s', $vStart->format('H:i'), $vEnd->format('H:i'), $vStart->format('d/m/Y')),
            MaintenanceRecurrence::Weekly => \sprintf(
                'chaque %s de %s à %s%s, à partir du %s',
                self::DAY_NAMES[(int) $vStart->format('N')],
                $vStart->format('H:i'),
                $vStart->format('N') === $vEnd->format('N') ? '' : self::DAY_NAMES[(int) $vEnd->format('N')].' ',
                $vEnd->format('H:i'),
                $vStart->format('d/m/Y'),
            ),
        };

        return $rSchedule;
    }

    public function getDurationSeconds(): int
    {
        return $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonitor(): ?Monitor
    {
        return $this->monitor;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getRecurrence(): MaintenanceRecurrence
    {
        return $this->recurrence;
    }
}
