<?php

namespace App\Tests\Controller\Api;

use App\Entity\ApiToken;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MonitorApiControllerTest extends WebTestCase
{
    private KernelBrowser $aClient;
    private EntityManagerInterface $aEntityManager;
    private string $aToken;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class, ApiToken::class, User::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }

        $vUser = new User('admin@example.com');
        [$this->aToken, $vApiToken] = ApiToken::generate($vUser, 'téléphone', new \DateTimeImmutable());
        $this->aEntityManager->persist($vUser);
        $this->aEntityManager->persist($vApiToken);
        $this->aEntityManager->flush();
    }

    public function testMissingTokenIsRejected(): void
    {
        $this->aClient->request('GET', '/api/monitors');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->aClient->request('GET', '/api/monitors', server: ['HTTP_AUTHORIZATION' => 'Bearer nm_invalide']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testListAndShowMonitors(): void
    {
        $vMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $vNow = new \DateTimeImmutable();
        $vMonitor->recordPing(PingKind::Success, $vNow);
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->persist(new Ping($vMonitor, PingKind::Success, $vNow, 1200));
        $this->aEntityManager->flush();

        $vList = $this->getJson('/api/monitors');
        self::assertSame('Sauvegarde restic', $vList['monitors'][0]['name']);
        self::assertSame('up', $vList['monitors'][0]['status']);
        self::assertNull($vList['monitors'][0]['open_incident']);

        $vDetail = $this->getJson('/api/monitors/'.$vMonitor->getId());
        self::assertSame(1200, $vDetail['pings'][0]['duration_ms']);
        self::assertSame([], $vDetail['incidents']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $pUrl): array
    {
        $this->aClient->request('GET', $pUrl, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->aToken]);
        self::assertResponseIsSuccessful();
        $rData = json_decode((string) $this->aClient->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $rData;
    }
}
