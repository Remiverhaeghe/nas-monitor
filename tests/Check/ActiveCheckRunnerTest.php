<?php

namespace App\Tests\Check;

use App\Check\ActiveCheckRunner;
use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActiveCheckRunnerTest extends KernelTestCase
{
    public function testOnlyDueChecksAreRunAndFailuresAreRecorded(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class] as $vClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }

        $vServer = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($vServer);
        $vOpen = (string) stream_socket_get_name($vServer, false);
        $vClosedServer = stream_socket_server('tcp://127.0.0.1:0');
        $vClosed = (string) stream_socket_get_name($vClosedServer, false);
        fclose($vClosedServer);

        $vNow = new \DateTimeImmutable();
        $vUp = new Monitor('Ouvert', MonitorType::Tcp, 60, 60, $vNow->modify('-1 day'));
        $vUp->setTarget($vOpen);
        $vDown = new Monitor('Fermé', MonitorType::Tcp, 60, 60, $vNow->modify('-1 day'));
        $vDown->setTarget($vClosed);
        $vNotDue = new Monitor('Récent', MonitorType::Tcp, 3600, 60, $vNow->modify('-1 day'));
        $vNotDue->setTarget($vOpen);
        $vNotDue->recordPing(PingKind::Success, $vNow);
        foreach ([$vUp, $vDown, $vNotDue] as $vMonitor) {
            $vEntityManager->persist($vMonitor);
        }
        $vEntityManager->flush();

        $vCount = static::getContainer()->get(ActiveCheckRunner::class)->runDueChecks();

        self::assertSame(2, $vCount);
        self::assertSame(1, (int) $vEntityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM ping WHERE kind = 'fail'"));
        fclose($vServer);
    }
}
