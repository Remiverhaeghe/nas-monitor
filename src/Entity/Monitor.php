<?php

namespace App\Entity;

use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\NotificationChannel;
use App\Enum\PingKind;
use App\Repository\MonitorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MonitorRepository::class)]
class Monitor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 16, enumType: MonitorType::class)]
    private MonitorType $type;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $target = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $groupName = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $pingTokenHash = null;

    #[ORM\Column]
    #[Assert\Range(min: 60, max: 2678400)]
    private int $expectedIntervalSeconds;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 604800)]
    private int $graceSeconds;

    #[ORM\Column]
    private bool $paused = false;

    #[ORM\Column(length: 16, enumType: MonitorStatus::class)]
    private MonitorStatus $status = MonitorStatus::New;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPingAt = null;

    #[ORM\Column(length: 16, nullable: true, enumType: PingKind::class)]
    private ?PingKind $lastPingKind = null;

    #[ORM\Column(length: 16, enumType: NotificationChannel::class, options: ['default' => 'email'])]
    private NotificationChannel $notificationChannel = NotificationChannel::Email;

    /**
     * Moniteur dont dépend celui-ci (le NAS pour ses services) : tant qu'il est en panne, les alertes de celui-ci attendent.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Monitor $parent = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $publicVisible = false;

    /**
     * @var array<string, float>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $metricLimits = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Évite qu'un ping et la vérification planifiée écrasent mutuellement leurs modifications.
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    public function __construct(
        string $pName,
        MonitorType $pType,
        int $pExpectedIntervalSeconds,
        int $pGraceSeconds,
        \DateTimeImmutable $pCreatedAt,
    ) {
        $this->name = $pName;
        $this->type = $pType;
        $this->expectedIntervalSeconds = $pExpectedIntervalSeconds;
        $this->graceSeconds = $pGraceSeconds;
        $this->createdAt = $pCreatedAt;
    }

    public static function hashToken(string $pToken): string
    {
        $rHash = hash('sha256', $pToken);

        return $rHash;
    }

    /**
     * Seule l'empreinte est conservée : le jeton en clair n'est disponible qu'au retour de cet appel.
     */
    public function rotatePingToken(): string
    {
        $rToken = bin2hex(random_bytes(32));
        $this->pingTokenHash = self::hashToken($rToken);

        return $rToken;
    }

    /**
     * Retourne vrai si le statut a changé.
     */
    public function recordPing(PingKind $pKind, \DateTimeImmutable $pReceivedAt): bool
    {
        if (PingKind::Start === $pKind) {
            return false;
        }

        $this->lastPingAt = $pReceivedAt;
        $this->lastPingKind = $pKind;
        $rChanged = $this->refreshStatus($pReceivedAt);

        return $rChanged;
    }

    /**
     * Retourne vrai si le statut a changé.
     */
    public function refreshStatus(\DateTimeImmutable $pNow): bool
    {
        $vStatus = $this->evaluateStatus($pNow);
        $rChanged = $vStatus !== $this->status;
        $this->status = $vStatus;

        return $rChanged;
    }

    public function evaluateStatus(\DateTimeImmutable $pNow): MonitorStatus
    {
        if ($this->paused) {
            return MonitorStatus::Paused;
        }
        if (null === $this->lastPingAt) {
            return MonitorStatus::New;
        }
        if (PingKind::Fail === $this->lastPingKind) {
            return MonitorStatus::Down;
        }

        $vElapsed = $pNow->getTimestamp() - $this->lastPingAt->getTimestamp();
        $rStatus = match (true) {
            $vElapsed <= $this->expectedIntervalSeconds => MonitorStatus::Up,
            $vElapsed <= $this->expectedIntervalSeconds + $this->graceSeconds => MonitorStatus::Late,
            default => MonitorStatus::Down,
        };

        return $rStatus;
    }

    /**
     * Pour un contrôle actif : vrai si l'intervalle est écoulé depuis le dernier contrôle.
     */
    public function isCheckDue(\DateTimeImmutable $pNow): bool
    {
        if ($this->paused || !$this->type->isActive()) {
            return false;
        }
        if (null === $this->lastPingAt) {
            return true;
        }

        $rDue = $pNow->getTimestamp() - $this->lastPingAt->getTimestamp() >= $this->expectedIntervalSeconds;

        return $rDue;
    }

    public function pause(): void
    {
        $this->paused = true;
        $this->status = MonitorStatus::Paused;
    }

    /**
     * Repart de zéro pour ne pas déclencher d'alerte sur un ping antérieur à la pause.
     */
    public function resume(): void
    {
        $this->paused = false;
        $this->lastPingAt = null;
        $this->lastPingKind = null;
        $this->status = MonitorStatus::New;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $pName): void
    {
        $this->name = $pName;
    }

    public function getType(): MonitorType
    {
        return $this->type;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $pTarget): void
    {
        $this->target = $pTarget;
    }

    public function getExpectedIntervalSeconds(): int
    {
        return $this->expectedIntervalSeconds;
    }

    public function setExpectedIntervalSeconds(int $pExpectedIntervalSeconds): void
    {
        $this->expectedIntervalSeconds = $pExpectedIntervalSeconds;
    }

    public function getGraceSeconds(): int
    {
        return $this->graceSeconds;
    }

    public function setGraceSeconds(int $pGraceSeconds): void
    {
        $this->graceSeconds = $pGraceSeconds;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function getStatus(): MonitorStatus
    {
        return $this->status;
    }

    public function getLastPingAt(): ?\DateTimeImmutable
    {
        return $this->lastPingAt;
    }

    public function getLastPingKind(): ?PingKind
    {
        return $this->lastPingKind;
    }

    public function getParent(): ?Monitor
    {
        return $this->parent;
    }

    public function setParent(?Monitor $pParent): void
    {
        $this->parent = $pParent;
    }

    public function isParentDown(): bool
    {
        return MonitorStatus::Down === $this->parent?->getStatus();
    }

    public function isPublicVisible(): bool
    {
        return $this->publicVisible;
    }

    public function setPublicVisible(bool $pPublicVisible): void
    {
        $this->publicVisible = $pPublicVisible;
    }

    /**
     * @return array<string, float>
     */
    public function getMetricLimits(): array
    {
        return $this->metricLimits;
    }

    /**
     * @param array<string, float> $pMetricLimits
     */
    public function setMetricLimits(array $pMetricLimits): void
    {
        $this->metricLimits = $pMetricLimits;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $pGroupName): void
    {
        $this->groupName = $pGroupName;
    }

    public function getNotificationChannel(): NotificationChannel
    {
        return $this->notificationChannel;
    }

    public function setNotificationChannel(NotificationChannel $pNotificationChannel): void
    {
        $this->notificationChannel = $pNotificationChannel;
    }

    public function hasPingToken(): bool
    {
        return null !== $this->pingTokenHash;
    }
}
