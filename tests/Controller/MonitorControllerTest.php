<?php

namespace App\Tests\Controller;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\IncidentCause;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class MonitorControllerTest extends WebTestCase
{
    private KernelBrowser $aClient;
    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class, User::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }

        $vUser = new User('admin@example.com');
        $this->aEntityManager->persist($vUser);
        $this->aEntityManager->flush();
        $this->aClient->loginUser($vUser);
    }

    public function testCreateMonitorShowsTokenOnlyOnce(): void
    {
        $vCrawler = $this->aClient->request('GET', '/monitors/new');
        $this->aClient->submit($vCrawler->selectButton('Enregistrer')->form([
            'monitor_form[name]' => 'Sauvegarde restic',
            'monitor_form[intervalMinutes]' => '1440',
            'monitor_form[graceMinutes]' => '30',
        ]));

        self::assertResponseRedirects();
        $vCrawler = $this->aClient->followRedirect();
        $vToken = $vCrawler->filter('.token pre')->first()->text();

        $vMonitor = $this->findMonitorByName('Sauvegarde restic');
        self::assertSame(86400, $vMonitor->getExpectedIntervalSeconds());
        self::assertSame(1800, $vMonitor->getGraceSeconds());
        self::assertSame($vMonitor->getId(), $this->findMonitorByToken($vToken)?->getId());

        $this->aClient->request('GET', '/monitors/'.$vMonitor->getId());
        self::assertSelectorNotExists('.token');
    }

    public function testInvalidFormIsRejected(): void
    {
        $vCrawler = $this->aClient->request('GET', '/monitors/new');
        $this->aClient->submit($vCrawler->selectButton('Enregistrer')->form([
            'monitor_form[name]' => '',
            'monitor_form[intervalMinutes]' => '0',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->aEntityManager->getRepository(Monitor::class)->count([]));
    }

    public function testEditMonitor(): void
    {
        $vMonitor = $this->createMonitor();

        $vCrawler = $this->aClient->request('GET', '/monitors/'.$vMonitor->getId().'/edit');
        $this->aClient->submit($vCrawler->selectButton('Enregistrer')->form([
            'monitor_form[name]' => 'Sauvegarde renommée',
            'monitor_form[intervalMinutes]' => '120',
        ]));

        self::assertResponseRedirects('/monitors/'.$vMonitor->getId());
        $vMonitor = $this->reload($vMonitor);
        self::assertSame('Sauvegarde renommée', $vMonitor->getName());
        self::assertSame(7200, $vMonitor->getExpectedIntervalSeconds());
    }

    public function testPauseClosesOpenIncidentWithoutAlert(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->recordPing(PingKind::Fail, new \DateTimeImmutable());
        $vIncident = new Incident($vMonitor, IncidentCause::Failed, new \DateTimeImmutable());
        $this->aEntityManager->persist($vIncident);
        $this->aEntityManager->flush();

        $this->submitAction($vMonitor, 'Mettre en pause');

        self::assertSame(MonitorStatus::Paused, $this->reload($vMonitor)->getStatus());
        self::assertNotNull($this->aEntityManager->find(Incident::class, $vIncident->getId())?->getResolvedAt());
        self::assertQueuedEmailCount(0);
    }

    public function testResumeWaitsForNextPing(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitor->pause();
        $this->aEntityManager->flush();

        $this->submitAction($vMonitor, 'Reprendre');

        $vMonitor = $this->reload($vMonitor);
        self::assertFalse($vMonitor->isPaused());
        self::assertSame(MonitorStatus::New, $vMonitor->getStatus());
    }

    public function testRotateTokenInvalidatesPreviousToken(): void
    {
        $vMonitor = $this->createMonitor();
        $vOldToken = $vMonitor->rotatePingToken();
        $this->aEntityManager->flush();

        $vCrawler = $this->submitAction($vMonitor, 'Régénérer le jeton');
        $vNewToken = $vCrawler->filter('.token pre')->first()->text();

        self::assertNull($this->findMonitorByToken($vOldToken));
        self::assertSame($vMonitor->getId(), $this->findMonitorByToken($vNewToken)?->getId());
    }

    public function testDeleteMonitor(): void
    {
        $vMonitor = $this->createMonitor();
        $vMonitorId = $vMonitor->getId();

        $this->submitAction($vMonitor, 'Supprimer');

        self::assertSelectorTextContains('.flash', 'supprimé');
        $this->aEntityManager->clear();
        self::assertNull($this->aEntityManager->find(Monitor::class, $vMonitorId));
    }

    public function testActionWithoutCsrfTokenIsRefused(): void
    {
        $vMonitor = $this->createMonitor();

        $this->aClient->request('POST', '/monitors/'.$vMonitor->getId().'/delete');

        self::assertResponseRedirects('http://localhost/login');
        $this->aEntityManager->clear();
        self::assertNotNull($this->aEntityManager->find(Monitor::class, $vMonitor->getId()));
    }

    private function submitAction(Monitor $pMonitor, string $pButtonLabel): Crawler
    {
        $vCrawler = $this->aClient->request('GET', '/monitors/'.$pMonitor->getId());
        $this->aClient->submit($vCrawler->selectButton($pButtonLabel)->form());
        self::assertResponseRedirects();
        $rCrawler = $this->aClient->followRedirect();

        return $rCrawler;
    }

    private function createMonitor(): Monitor
    {
        $rMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }

    private function reload(Monitor $pMonitor): Monitor
    {
        $this->aEntityManager->clear();
        $rMonitor = $this->aEntityManager->find(Monitor::class, $pMonitor->getId());
        self::assertNotNull($rMonitor);

        return $rMonitor;
    }

    private function findMonitorByName(string $pName): Monitor
    {
        $rMonitor = $this->aEntityManager->getRepository(Monitor::class)->findOneBy(['name' => $pName]);
        self::assertNotNull($rMonitor);

        return $rMonitor;
    }

    private function findMonitorByToken(string $pToken): ?Monitor
    {
        $this->aEntityManager->clear();
        $rMonitor = $this->aEntityManager->getRepository(Monitor::class)->findOneBy(['pingTokenHash' => Monitor::hashToken($pToken)]);

        return $rMonitor;
    }
}
