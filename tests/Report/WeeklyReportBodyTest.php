<?php

namespace App\Tests\Report;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Report\WeeklyReportSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WeeklyReportBodyTest extends KernelTestCase
{
    public function testReportWithoutMonitorSaysSo(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }

        $vBody = static::getContainer()->get(WeeklyReportSender::class)->buildBody(
            new \DateTimeImmutable('2026-11-04 00:00:00'),
            new \DateTimeImmutable('2026-11-11 00:00:00'),
        );

        self::assertSame("Aucun moniteur configuré.\n", $vBody);
    }
}
