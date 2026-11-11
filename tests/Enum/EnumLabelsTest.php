<?php

namespace App\Tests\Enum;

use App\Enum\AuditAction;
use App\Enum\MonitorStatus;
use App\Enum\PingKind;
use PHPUnit\Framework\TestCase;

class EnumLabelsTest extends TestCase
{
    public function testEveryAuditActionHasADistinctLabel(): void
    {
        $vLabels = array_map(static fn (AuditAction $pAction): string => $pAction->label(), AuditAction::cases());

        self::assertNotContains('', $vLabels);
        self::assertCount(\count(AuditAction::cases()), array_unique($vLabels));
    }

    public function testEveryPingKindHasADistinctLabel(): void
    {
        $vLabels = array_map(static fn (PingKind $pKind): string => $pKind->label(), PingKind::cases());

        self::assertSame(['Début', 'Succès', 'Échec'], $vLabels);
    }

    public function testMonitorStatusLabelsAndSeverityOrder(): void
    {
        $vLabels = array_map(static fn (MonitorStatus $pStatus): string => $pStatus->label(), MonitorStatus::cases());
        self::assertCount(\count(MonitorStatus::cases()), array_unique($vLabels));

        self::assertSame(
            [MonitorStatus::Down, MonitorStatus::Late, MonitorStatus::New, MonitorStatus::Up, MonitorStatus::Paused],
            $this->sortedBySeverity(),
        );
    }

    /**
     * @return list<MonitorStatus>
     */
    private function sortedBySeverity(): array
    {
        $vStatuses = MonitorStatus::cases();
        usort($vStatuses, static fn (MonitorStatus $pA, MonitorStatus $pB): int => $pA->severity() <=> $pB->severity());

        return $vStatuses;
    }
}
