<?php

namespace App\Entity;

use App\Enum\PingKind;
use App\Repository\PingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PingRepository::class)]
#[ORM\Index(name: 'idx_ping_monitor_received', columns: ['monitor_id', 'received_at'])]
#[ORM\Index(name: 'idx_ping_received', columns: ['received_at'])]
class Ping
{
    public const MESSAGE_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Monitor $monitor;

    #[ORM\Column(length: 16, enumType: PingKind::class)]
    private PingKind $kind;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $sizeBytes;

    #[ORM\Column(length: self::MESSAGE_MAX_LENGTH, nullable: true)]
    private ?string $message;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $sourceIp;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $anomaly = null;

    public function __construct(
        Monitor $pMonitor,
        PingKind $pKind,
        \DateTimeImmutable $pReceivedAt,
        ?int $pDurationMs = null,
        ?int $pSizeBytes = null,
        ?string $pMessage = null,
        ?string $pSourceIp = null,
    ) {
        $this->monitor = $pMonitor;
        $this->kind = $pKind;
        $this->receivedAt = $pReceivedAt;
        $this->durationMs = $pDurationMs;
        $this->sizeBytes = $pSizeBytes;
        $this->message = null === $pMessage ? null : mb_substr($pMessage, 0, self::MESSAGE_MAX_LENGTH);
        $this->sourceIp = $pSourceIp;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonitor(): Monitor
    {
        return $this->monitor;
    }

    public function getKind(): PingKind
    {
        return $this->kind;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSourceIp(): ?string
    {
        return $this->sourceIp;
    }

    public function markAnomaly(string $pAnomaly): void
    {
        $this->anomaly = mb_substr($pAnomaly, 0, 255);
    }

    public function getAnomaly(): ?string
    {
        return $this->anomaly;
    }
}
