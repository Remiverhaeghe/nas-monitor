<?php

namespace App\Tests\Controller;

use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\Security\BackupCodeGenerator;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardControllerTest extends WebTestCase
{
    private const EMAIL = 'admin@example.com';
    private const PASSWORD = 'mot-de-passe-de-test';

    private KernelBrowser $aClient;
    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class, User::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->aClient->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser();

        $this->submitLogin(self::PASSWORD);

        self::assertResponseRedirects('/');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('h1', 'Moniteurs');
    }

    public function testLoginWithWrongPasswordShowsError(): void
    {
        $this->createUser();

        $this->submitLogin('mauvais-mot-de-passe');
        $this->aClient->followRedirect();

        self::assertSelectorExists('.error');
    }

    public function testTotpCodeIsRequiredWhenEnabled(): void
    {
        $vUser = $this->createUser();
        $vSecret = $this->enableTotp($vUser);

        self::assertStringNotContainsString(
            $vSecret,
            (string) $this->aEntityManager->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$vUser->getId()]),
        );

        $this->submitLogin(self::PASSWORD);
        self::assertResponseRedirects('/');
        $this->aClient->followRedirect();
        self::assertResponseRedirects('/2fa');
        $vCrawler = $this->aClient->followRedirect();

        $this->aClient->submit($vCrawler->selectButton('Valider')->form(['_auth_code' => '000000']));
        $vCrawler = $this->aClient->followRedirect();
        self::assertSelectorExists('.error');

        $this->aClient->submit($vCrawler->selectButton('Valider')->form(['_auth_code' => TOTP::createFromSecret($vSecret)->now()]));
        self::assertResponseRedirects('/');
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('h1', 'Moniteurs');
    }

    public function testUnreadableTotpSecretBlocksLogin(): void
    {
        $vUser = $this->createUser();
        $vUser->enableTotp('JBSWY3DPEHPK3PXP', (new SecretCipher(SecretCipher::generateKey()))->encrypt('JBSWY3DPEHPK3PXP'));
        $this->aEntityManager->flush();

        $this->submitLogin(self::PASSWORD);
        self::assertResponseStatusCodeSame(500);

        $this->aClient->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testBackupCodeWorksOnlyOnce(): void
    {
        $vUser = $this->createUser();
        $this->enableTotp($vUser);
        $vCodes = BackupCodeGenerator::generate();
        $vUser->replaceBackupCodes($vCodes);
        $this->aEntityManager->flush();

        $this->submitTotpStep(strtoupper($vCodes[0]));
        self::assertResponseRedirects('/');
        $this->aClient->restart();

        $this->submitTotpStep($vCodes[0]);
        $this->aClient->followRedirect();
        self::assertSelectorExists('.error');
    }

    public function testDashboardListsMonitorsDownFirst(): void
    {
        $this->aClient->loginUser($this->createUser());
        $this->createMonitor('B - opérationnel', PingKind::Success);
        $this->createMonitor('A - jamais pingé', null);
        $this->createMonitor('C - en échec', PingKind::Fail);

        $vCrawler = $this->aClient->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['C - en échec', 'A - jamais pingé', 'B - opérationnel'],
            $vCrawler->filter('tbody tr td:first-child')->each(static fn ($pCell): string => $pCell->text()),
        );
    }

    public function testMonitorPageShowsPings(): void
    {
        $this->aClient->loginUser($this->createUser());
        $vMonitor = $this->createMonitor('Sauvegarde restic', PingKind::Success);

        $this->aClient->request('GET', '/monitors/'.$vMonitor->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sauvegarde restic');
        self::assertSelectorTextContains('table', 'Succès');
    }

    public function testDashboardGroupsMonitorsAndListensForUpdates(): void
    {
        $this->aClient->loginUser($this->createUser());
        $vMonitor = new Monitor('Photos', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('-1 day'));
        $vMonitor->setGroupName('Sauvegardes');
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->flush();

        $this->aClient->request('GET', '/');

        self::assertSelectorTextContains('.group-row', 'Sauvegardes');
        self::assertSelectorTextContains('tbody tr:nth-child(2)', '100,0 %');
        self::assertSelectorExists('turbo-mercure-stream-source[private]');
    }

    public function testUnknownMonitorReturnsNotFound(): void
    {
        $this->aClient->loginUser($this->createUser());

        $this->aClient->request('GET', '/monitors/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function submitLogin(string $pPassword): void
    {
        $vCrawler = $this->aClient->request('GET', '/login');
        $vForm = $vCrawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => $pPassword,
        ]);
        $this->aClient->submit($vForm);
    }

    private function createUser(): User
    {
        $rUser = new User(self::EMAIL);
        $vHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $rUser->setPassword($vHasher->hashPassword($rUser, self::PASSWORD));
        $this->aEntityManager->persist($rUser);
        $this->aEntityManager->flush();

        return $rUser;
    }

    private function enableTotp(User $pUser): string
    {
        $rSecret = static::getContainer()->get(TotpAuthenticatorInterface::class)->generateSecret();
        $pUser->enableTotp($rSecret, static::getContainer()->get(SecretCipher::class)->encrypt($rSecret));
        $this->aEntityManager->flush();

        return $rSecret;
    }

    private function submitTotpStep(string $pCode): void
    {
        $this->submitLogin(self::PASSWORD);
        $this->aClient->followRedirect();
        $vCrawler = $this->aClient->followRedirect();
        $this->aClient->submit($vCrawler->selectButton('Valider')->form(['_auth_code' => $pCode]));
    }

    private function createMonitor(string $pName, ?PingKind $pPingKind): Monitor
    {
        $vNow = new \DateTimeImmutable();
        $rMonitor = new Monitor($pName, MonitorType::Heartbeat, 3600, 600, $vNow);
        if (null !== $pPingKind) {
            $rMonitor->recordPing($pPingKind, $vNow);
            $this->aEntityManager->persist(new Ping($rMonitor, $pPingKind, $vNow));
        }
        $this->aEntityManager->persist($rMonitor);
        $this->aEntityManager->flush();

        return $rMonitor;
    }
}
