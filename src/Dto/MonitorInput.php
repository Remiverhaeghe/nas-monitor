<?php

namespace App\Dto;

use App\Entity\Monitor;
use App\Enum\MonitorType;
use App\Enum\NotificationChannel;
use App\Metric\MetricLimits;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MonitorInput
{
    private const HOST_PORT_PATTERN = '/^[A-Za-z0-9.-]+:\d{1,5}$/';
    private const HOST_PATTERN = '/^[A-Za-z0-9.-]+$/';
    private const DNS_PATTERN = '/^[A-Za-z0-9.-]+(=[0-9A-Fa-f.:]+)?$/';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotNull]
    public ?MonitorType $type = MonitorType::Heartbeat;

    #[Assert\Length(max: 255)]
    public ?string $target = null;

    #[Assert\Length(max: 50)]
    public ?string $groupName = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 44640)]
    public ?int $intervalMinutes = 60;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 10080)]
    public ?int $graceMinutes = 10;

    #[Assert\NotNull]
    public ?NotificationChannel $notificationChannel = NotificationChannel::Email;

    public ?Monitor $parent = null;

    public bool $publicVisible = false;

    #[Assert\Length(max: 500)]
    public ?string $metricLimits = null;

    /**
     * Renseigné par le contrôleur : un moniteur dont d'autres dépendent ne peut pas dépendre à son tour d'un autre.
     */
    public bool $hasChildren = false;

    public static function fromMonitor(Monitor $pMonitor): self
    {
        $rInput = new self();
        $rInput->name = $pMonitor->getName();
        $rInput->type = $pMonitor->getType();
        $rInput->target = $pMonitor->getTarget();
        $rInput->groupName = $pMonitor->getGroupName();
        $rInput->intervalMinutes = intdiv($pMonitor->getExpectedIntervalSeconds(), 60);
        $rInput->graceMinutes = intdiv($pMonitor->getGraceSeconds(), 60);
        $rInput->notificationChannel = $pMonitor->getNotificationChannel();
        $rInput->parent = $pMonitor->getParent();
        $rInput->publicVisible = $pMonitor->isPublicVisible();
        $rInput->metricLimits = MetricLimits::format($pMonitor->getMetricLimits());

        return $rInput;
    }

    /**
     * @return array<string, float>
     */
    public function parsedMetricLimits(): array
    {
        $rLimits = MetricLimits::parse((string) $this->metricLimits);

        return $rLimits;
    }

    #[Assert\Callback]
    public function validateOptions(ExecutionContextInterface $pContext): void
    {
        if (null !== $this->parent && $this->hasChildren) {
            $pContext->buildViolation('D\'autres moniteurs dépendent de celui-ci : il ne peut pas dépendre à son tour d\'un autre.')->atPath('parent')->addViolation();
        }

        try {
            $this->parsedMetricLimits();
        } catch (\InvalidArgumentException $vException) {
            $pContext->buildViolation($vException->getMessage())->atPath('metricLimits')->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $pContext): void
    {
        $vTarget = trim((string) $this->target);

        $vError = match ($this->type) {
            MonitorType::Http => 1 === preg_match('#^https?://#i', $vTarget) && false !== filter_var($vTarget, \FILTER_VALIDATE_URL)
                ? null : 'URL attendue, commençant par http:// ou https://.',
            MonitorType::Tcp, MonitorType::Tls => 1 === preg_match(self::HOST_PORT_PATTERN, $vTarget)
                ? null : 'Cible attendue au format hôte:port, par exemple nas.local:443.',
            MonitorType::Dns => 1 === preg_match(self::DNS_PATTERN, $vTarget)
                ? null : 'Nom attendu, éventuellement suivi de =adresse, par exemple nas.exemple.fr=192.168.1.10.',
            MonitorType::Icmp => 1 === preg_match(self::HOST_PATTERN, $vTarget)
                ? null : 'Nom d\'hôte ou adresse IPv4 attendu, par exemple 192.168.1.1.',
            default => '' === $vTarget ? null : 'Une tâche planifiée n\'a pas de cible.',
        };

        if (null !== $vError) {
            $pContext->buildViolation($vError)->atPath('target')->addViolation();
        }
    }
}
