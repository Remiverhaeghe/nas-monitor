<?php

namespace App\Entity;

use App\Repository\MaintenanceWindowRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plage pendant laquelle aucune alerte n'est envoyée ; sans moniteur, elle s'applique à tous.
 */
#[ORM\Entity(repositoryClass: MaintenanceWindowRepository::class)]
#[ORM\Index(name: 'idx_maintenance_period', columns: ['starts_at', 'ends_at'])]
class MaintenanceWindow
{
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

    public function __construct(?Monitor $pMonitor, \DateTimeImmutable $pStartsAt, \DateTimeImmutable $pEndsAt, string $pReason)
    {
        $this->monitor = $pMonitor;
        $this->startsAt = $pStartsAt;
        $this->endsAt = $pEndsAt;
        $this->reason = $pReason;
    }

    public function isActive(\DateTimeImmutable $pNow): bool
    {
        $rActive = $this->startsAt <= $pNow && $pNow < $this->endsAt;

        return $rActive;
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
}
