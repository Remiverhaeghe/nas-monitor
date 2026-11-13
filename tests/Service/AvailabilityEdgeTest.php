<?php

namespace App\Tests\Service;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\IncidentCause;
use App\Enum\MonitorType;
use App\Service\AvailabilityCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AvailabilityEdgeTest extends KernelTestCase
{
    public function testZeroLengthIncidentCostsNothingAndTwoIncidentsAdd(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }
        $vNow = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vInstant = new Monitor('Instantané', MonitorType::Heartbeat, 3600, 600, $vNow->modify('-30 days'));
        $vTwice = new Monitor('Deux fois', MonitorType::Heartbeat, 3600, 600, $vNow->modify('-30 days'));
        $vEntityManager->persist($vInstant);
        $vEntityManager->persist($vTwice);
        $vZero = new Incident($vInstant, IncidentCause::Missed, $vNow->modify('-2 days'));
        $vZero->resolve($vNow->modify('-2 days'));
        $vEntityManager->persist($vZero);
        foreach (['-3 days', '-1 day'] as $vStart) {
            $vIncident = new Incident($vTwice, IncidentCause::Failed, $vNow->modify($vStart));
            $vIncident->resolve($vNow->modify($vStart.' +1 hour'));
            $vEntityManager->persist($vIncident);
        }
        $vEntityManager->flush();

        $vAvailability = static::getContainer()->get(AvailabilityCalculator::class)
            ->compute([$vInstant, $vTwice], $vNow->modify('-7 days'), $vNow);

        self::assertSame(100.0, $vAvailability[$vInstant->getId()]);
        self::assertEqualsWithDelta(100 * (1 - 7200 / 604800), $vAvailability[$vTwice->getId()], 0.0000001);
    }
}
