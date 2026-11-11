<?php

namespace App\Tests\E2E;

use App\Entity\AuditLog;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use App\Enum\MonitorType;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Parcours dans un vrai navigateur (Chromium), serveur hors debug pour que la CSP s'applique.
 */
class BrowserTest extends PantherTestCase
{
    private const EMAIL = 'e2e@example.com';
    private const PASSWORD = 'mot-de-passe-de-test';
    private const SERVER_ENV = [
        'APP_DEBUG' => '0',
        // Le hub doit partager l'hôte de la page pour que le cookie d'abonnement Mercure soit accepté
        'MERCURE_PUBLIC_URL' => 'http://127.0.0.1:9080/.well-known/mercure',
    ];

    private Monitor $aMonitor;

    protected function setUp(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([AuditLog::class, Incident::class, Ping::class, Monitor::class, User::class] as $vEntityClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        static::getContainer()->get('cache.rate_limiter')->clear();

        $vUser = new User(self::EMAIL);
        $vUser->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($vUser, self::PASSWORD));
        $this->aMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $vEntityManager->persist($vUser);
        $vEntityManager->persist($this->aMonitor);
        $vEntityManager->flush();
    }

    public function testDashboardLoadsWithoutCspViolation(): void
    {
        $vClient = $this->login();

        self::assertSelectorTextContains('table', 'Sauvegarde restic');
        self::assertSame([], $this->browserErrors($vClient));
    }

    public function testDeletionAsksForConfirmation(): void
    {
        $vClient = $this->login();
        $vClient->request('GET', '/monitors/'.$this->aMonitor->getId());
        $vDelete = WebDriverBy::xpath('//button[normalize-space()="Supprimer"]');

        $vClient->findElement($vDelete)->click();
        $vClient->getWebDriver()->switchTo()->alert()->dismiss();
        self::assertSelectorTextContains('h1', 'Sauvegarde restic');

        $vClient->findElement($vDelete)->click();
        $vClient->getWebDriver()->switchTo()->alert()->accept();
        $vClient->waitForElementToContain('.flash', 'supprimé');
        self::assertSame([], $this->browserErrors($vClient));
    }

    private function login(): Client
    {
        $rClient = static::createPantherClient(['env' => self::SERVER_ENV]);
        $rClient->request('GET', '/login');
        $rClient->submitForm('Se connecter', ['_username' => self::EMAIL, '_password' => self::PASSWORD]);
        $rClient->waitForElementToContain('h1', 'Moniteurs');

        return $rClient;
    }

    /**
     * Erreurs de la console, hors connexion au hub Mercure, absent du serveur de test.
     *
     * @return list<string>
     */
    private function browserErrors(Client $pClient): array
    {
        $rErrors = [];
        foreach ($pClient->getWebDriver()->manage()->getLog('browser') as $vEntry) {
            $vMessage = (string) $vEntry['message'];
            if ('SEVERE' === $vEntry['level'] && !str_contains($vMessage, '.well-known/mercure')) {
                $rErrors[] = $vMessage;
            }
        }

        return $rErrors;
    }
}
