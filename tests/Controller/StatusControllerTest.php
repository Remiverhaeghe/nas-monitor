<?php

namespace App\Tests\Controller;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatusControllerTest extends WebTestCase
{
    public function testOnlyPublicMonitorsAreShownWithoutLogin(): void
    {
        $vClient = static::createClient();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        $vPublic = new Monitor('Site familial', MonitorType::Http, 300, 60, new \DateTimeImmutable('-1 day'));
        $vPublic->setTarget('https://interne.exemple.fr/secret');
        $vPublic->setPublicVisible(true);
        $vPrivate = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('-1 day'));
        $vEntityManager->persist($vPublic);
        $vEntityManager->persist($vPrivate);
        $vEntityManager->flush();

        $vClient->request('GET', '/status');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Site familial');
        self::assertSelectorTextNotContains('main', 'Sauvegarde restic');
        self::assertSelectorTextNotContains('main', 'interne.exemple.fr');
        self::assertSelectorTextContains('.status-summary', 'Tous les services fonctionnent.');
        self::assertSelectorNotExists('header');
    }

    public function testPublicPageRevealsNoAddressPathOrVersion(): void
    {
        $vClient = static::createClient();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        $vMonitor = new Monitor('Gitea 1.22.3 sur 192.168.1.10:3000', MonitorType::Http, 300, 60, new \DateTimeImmutable('-1 day'));
        $vMonitor->setTarget('http://192.168.1.10:3000/api/healthz');
        $vMonitor->setGroupName('NAS /volume1');
        $vMonitor->setPublicVisible(true);
        $vEntityManager->persist($vMonitor);
        $vEntityManager->flush();

        $vClient->request('GET', '/status');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('tbody td.wrap', 'Gitea … sur …');
        self::assertSelectorTextSame('.group-row th', 'NAS …');
        $vResponse = $vClient->getResponse();
        $vContent = (string) $vResponse->getContent();
        foreach (['192.168', '1.22.3', '/volume1', 'healthz'] as $vSensitive) {
            self::assertStringNotContainsString($vSensitive, $vContent);
        }
        self::assertFalse($vResponse->headers->has('X-Powered-By'));
    }
}
