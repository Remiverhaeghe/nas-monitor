<?php

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\MonitorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuditControllerTest extends WebTestCase
{
    private const EMAIL = 'admin@example.com';
    private const PASSWORD = 'mot-de-passe-de-test';

    private KernelBrowser $aClient;
    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([AuditLog::class, Incident::class, Ping::class, Monitor::class, User::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        static::getContainer()->get('cache.rate_limiter')->clear();

        $vUser = new User(self::EMAIL);
        $vUser->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($vUser, self::PASSWORD));
        $this->aEntityManager->persist($vUser);
        $this->aEntityManager->flush();
    }

    public function testLoginsAreRecorded(): void
    {
        $this->submitLogin('mauvais-mot-de-passe');
        $this->submitLogin(self::PASSWORD);

        self::assertSame(
            [[AuditAction::LoginFailure, self::EMAIL], [AuditAction::Login, self::EMAIL]],
            array_map(static fn (AuditLog $pEntry): array => [$pEntry->getAction(), $pEntry->getActor()], $this->findEntries()),
        );
    }

    public function testMonitorChangesAreRecordedAndListed(): void
    {
        $this->submitLogin(self::PASSWORD);
        $vMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->flush();

        $vCrawler = $this->aClient->request('GET', '/monitors/'.$vMonitor->getId());
        $this->aClient->submit($vCrawler->selectButton('Mettre en pause')->form());

        $vEntries = $this->findEntries();
        self::assertSame(AuditAction::MonitorPause, end($vEntries)->getAction());
        self::assertStringContainsString('Sauvegarde restic', end($vEntries)->getDetail());

        $this->aClient->request('GET', '/audit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody tr:first-child', 'Mise en pause');
    }

    private function submitLogin(string $pPassword): void
    {
        $vCrawler = $this->aClient->request('GET', '/login');
        $this->aClient->submit($vCrawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => $pPassword,
        ]));
    }

    /**
     * @return list<AuditLog>
     */
    private function findEntries(): array
    {
        $rEntries = $this->aEntityManager->getRepository(AuditLog::class)->findBy([], ['id' => 'ASC']);

        return $rEntries;
    }
}
