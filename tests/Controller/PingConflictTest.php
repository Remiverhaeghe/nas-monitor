<?php

namespace App\Tests\Controller;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PingConflictTest extends WebTestCase
{
    public function testConcurrentUpdateAsksTheClientToRetry(): void
    {
        $vClient = static::createClient();
        $vClient->disableReboot();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vEntityManager->createQuery('DELETE FROM '.Ping::class)->execute();
        $vEntityManager->createQuery('DELETE FROM '.Monitor::class)->execute();
        static::getContainer()->get('cache.ping_limiter')->clear();

        $vMonitor = new Monitor('Sauvegarde', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('-1 day'));
        $vToken = $vMonitor->rotatePingToken();
        $vEntityManager->persist($vMonitor);
        $vEntityManager->flush();

        // Simule un autre processus qui modifie le moniteur entre la lecture et l'écriture
        $vEntityManager->getEventManager()->addEventListener(Events::onFlush, new class {
            public function onFlush(OnFlushEventArgs $pArgs): void
            {
                $pArgs->getObjectManager()->getConnection()->executeStatement('UPDATE monitor SET version = version + 1');
            }
        });

        $vClient->request('POST', '/ping/'.$vToken);

        self::assertResponseStatusCodeSame(503);
        self::assertSame('1', $vClient->getResponse()->headers->get('Retry-After'));
    }
}
