<?php

namespace App\Tests\Controller;

use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\MonitorStatus;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PingControllerTest extends WebTestCase
{
    private KernelBrowser $aClient;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $vEntityManager = $this->getEntityManager();
        $vEntityManager->createQuery('DELETE FROM '.Ping::class)->execute();
        $vEntityManager->createQuery('DELETE FROM '.Monitor::class)->execute();
        static::getContainer()->get('cache.ping_limiter')->clear();
    }

    public function testSuccessPingSetsMonitorUp(): void
    {
        [$vMonitorId, $vToken] = $this->createMonitor();

        $this->aClient->request('POST', '/ping/'.$vToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'up'], json_decode((string) $this->aClient->getResponse()->getContent(), true));
        self::assertSame(MonitorStatus::Up, $this->findMonitor($vMonitorId)->getStatus());
    }

    public function testFailPingStoresPayloadAndSetsMonitorDown(): void
    {
        [$vMonitorId, $vToken] = $this->createMonitor();

        $this->aClient->jsonRequest('POST', '/ping/'.$vToken.'/fail', [
            'duration_ms' => 1250,
            'size_bytes' => 5000000000,
            'message' => 'restic: repository locked',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(MonitorStatus::Down, $this->findMonitor($vMonitorId)->getStatus());

        $vPing = $this->getEntityManager()->getRepository(Ping::class)->findOneBy([]);
        self::assertNotNull($vPing);
        self::assertSame(PingKind::Fail, $vPing->getKind());
        self::assertSame(1250, $vPing->getDurationMs());
        self::assertSame(5000000000, $vPing->getSizeBytes());
        self::assertSame('restic: repository locked', $vPing->getMessage());
    }

    public function testUnknownTokenReturnsNotFound(): void
    {
        $this->aClient->request('POST', '/ping/'.str_repeat('a', 64));

        self::assertResponseStatusCodeSame(404);
    }

    public function testNegativeDurationIsRejected(): void
    {
        [, $vToken] = $this->createMonitor();

        $this->aClient->jsonRequest('POST', '/ping/'.$vToken, ['duration_ms' => -1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->getEntityManager()->getRepository(Ping::class)->count([]));
    }

    public function testMetricsAreStoredAndInvalidOnesRejected(): void
    {
        [, $vToken] = $this->createMonitor();

        $this->aClient->jsonRequest('POST', '/ping/'.$vToken, ['metrics' => ['Disque' => 'plein']]);
        self::assertResponseStatusCodeSame(422);

        $this->aClient->jsonRequest('POST', '/ping/'.$vToken, ['metrics' => ['disk_used_percent' => 82, 'temperature_c' => 44.5]]);
        self::assertResponseIsSuccessful();

        $vPing = $this->getEntityManager()->getRepository(Ping::class)->findOneBy([]);
        self::assertSame(['disk_used_percent' => 82.0, 'temperature_c' => 44.5], $vPing?->getMetrics());
    }

    public function testGetIsNotAllowed(): void
    {
        [, $vToken] = $this->createMonitor();

        $this->aClient->request('GET', '/ping/'.$vToken);

        self::assertResponseStatusCodeSame(405);
    }

    public function testTooManyPingsAreRejected(): void
    {
        [, $vToken] = $this->createMonitor();

        for ($vIndex = 0; $vIndex < 5; ++$vIndex) {
            $this->aClient->request('POST', '/ping/'.$vToken);
        }
        self::assertResponseIsSuccessful();

        $this->aClient->request('POST', '/ping/'.$vToken);
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    /**
     * @return array{int, string}
     */
    private function createMonitor(): array
    {
        $vEntityManager = $this->getEntityManager();
        $vMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $vToken = $vMonitor->rotatePingToken();
        $vEntityManager->persist($vMonitor);
        $vEntityManager->flush();
        $vEntityManager->clear();

        $rMonitor = [(int) $vMonitor->getId(), $vToken];

        return $rMonitor;
    }

    private function findMonitor(int $pMonitorId): Monitor
    {
        $vEntityManager = $this->getEntityManager();
        $vEntityManager->clear();
        $rMonitor = $vEntityManager->find(Monitor::class, $pMonitorId);
        self::assertNotNull($rMonitor);

        return $rMonitor;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $rEntityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $rEntityManager;
    }
}
