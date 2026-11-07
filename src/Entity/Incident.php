<?php

namespace App\Entity;

use App\Enum\IncidentCause;
use App\Repository\IncidentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Index(name: 'idx_incident_monitor_resolved', columns: ['monitor_id', 'resolved_at'])]
class Incident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Monitor $monitor;

    #[ORM\Column(length: 16, enumType: IncidentCause::class)]
    private IncidentCause $cause;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * Null tant qu'aucune alerte n'a pu partir, par exemple pendant une maintenance.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastNotifiedAt = null;

    public function __construct(Monitor $pMonitor, IncidentCause $pCause, \DateTimeImmutable $pStartedAt)
    {
        $this->monitor = $pMonitor;
        $this->cause = $pCause;
        $this->startedAt = $pStartedAt;
    }

    public function resolve(\DateTimeImmutable $pResolvedAt): void
    {
        $this->resolvedAt = $pResolvedAt;
    }

    public function markNotified(\DateTimeImmutable $pNow): void
    {
        $this->lastNotifiedAt = $pNow;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function isOpen(): bool
    {
        return null === $this->resolvedAt;
    }

    public function getDurationSeconds(\DateTimeImmutable $pNow): int
    {
        $rDuration = ($this->resolvedAt ?? $pNow)->getTimestamp() - $this->startedAt->getTimestamp();

        return $rDuration;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonitor(): Monitor
    {
        return $this->monitor;
    }

    public function getCause(): IncidentCause
    {
        return $this->cause;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
