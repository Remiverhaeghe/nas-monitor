<?php

namespace App\Entity;

use App\Enum\AuditAction;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * L'auteur est conservé sous forme de texte pour que l'entrée survive à la suppression du compte.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(name: 'idx_audit_log_occurred', columns: ['occurred_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 180)]
    private string $actor;

    #[ORM\Column(length: 32, enumType: AuditAction::class)]
    private AuditAction $action;

    #[ORM\Column(length: 255)]
    private string $detail;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress;

    public function __construct(\DateTimeImmutable $pOccurredAt, string $pActor, AuditAction $pAction, string $pDetail, ?string $pIpAddress)
    {
        $this->occurredAt = $pOccurredAt;
        $this->actor = mb_substr($pActor, 0, 180);
        $this->action = $pAction;
        $this->detail = mb_substr($pDetail, 0, 255);
        $this->ipAddress = $pIpAddress;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }
}
