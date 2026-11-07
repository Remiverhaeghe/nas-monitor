<?php

namespace App\Tests\Controller;

use App\Entity\MaintenanceWindow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MaintenanceControllerTest extends WebTestCase
{
    private KernelBrowser $aClient;
    private EntityManagerInterface $aEntityManager;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->aEntityManager->createQuery('DELETE FROM '.MaintenanceWindow::class)->execute();
        $this->aEntityManager->createQuery('DELETE FROM '.User::class)->execute();

        $vUser = new User('admin@example.com');
        $this->aEntityManager->persist($vUser);
        $this->aEntityManager->flush();
        $this->aClient->loginUser($vUser);
    }

    /**
     * Une plage globale restante couperait les alertes des autres tests.
     */
    protected function tearDown(): void
    {
        $this->aEntityManager->createQuery('DELETE FROM '.MaintenanceWindow::class)->execute();
        parent::tearDown();
    }

    public function testPlanAndDeleteMaintenance(): void
    {
        $vCrawler = $this->aClient->request('GET', '/maintenances');
        $this->aClient->submit($vCrawler->selectButton('Planifier')->form([
            'maintenance_form[startsAt]' => '2026-11-08T22:00',
            'maintenance_form[endsAt]' => '2026-11-08T23:30',
            'maintenance_form[reason]' => 'Remplacement d\'un disque',
        ]));

        self::assertResponseRedirects('/maintenances');
        $vWindow = $this->aEntityManager->getRepository(MaintenanceWindow::class)->findOneBy([]);
        self::assertNotNull($vWindow);
        self::assertNull($vWindow->getMonitor());
        self::assertSame('2026-11-08 21:00', $vWindow->getStartsAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));

        $vCrawler = $this->aClient->followRedirect();
        self::assertSelectorTextContains('table', 'Remplacement d\'un disque');

        $this->aClient->submit($vCrawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/maintenances');
        $this->aEntityManager->clear();
        self::assertSame(0, $this->aEntityManager->getRepository(MaintenanceWindow::class)->count([]));
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $vCrawler = $this->aClient->request('GET', '/maintenances');
        $this->aClient->submit($vCrawler->selectButton('Planifier')->form([
            'maintenance_form[startsAt]' => '2026-11-08T22:00',
            'maintenance_form[endsAt]' => '2026-11-08T21:00',
            'maintenance_form[reason]' => 'Erreur de saisie',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'La fin doit être postérieure au début.');
    }
}
