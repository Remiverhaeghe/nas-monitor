<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;
use App\Entity\Monitor;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Régénère les captures du README à partir des données de démonstration :
 * bin/phpunit --group screenshots
 */
#[Group('screenshots')]
class ScreenshotsTest extends PantherTestCase
{
    private const OUTPUT_DIR = __DIR__.'/../../docs/images';

    public function testCaptureDemoPages(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vLoader = new Loader();
        $vLoader->addFixture(static::getContainer()->get(AppFixtures::class));
        (new ORMExecutor($vEntityManager, new ORMPurger($vEntityManager)))->execute($vLoader->getFixtures());
        $vRestic = $vEntityManager->getRepository(Monitor::class)->findOneBy(['name' => 'Sauvegarde restic des photos']);
        self::assertNotNull($vRestic);

        $vClient = static::createPantherClient([
            'env' => ['APP_DEBUG' => '0', 'MERCURE_PUBLIC_URL' => 'http://127.0.0.1:9080/.well-known/mercure'],
        ]);
        $vClient->manage()->window()->setSize(new WebDriverDimension(1280, 900));

        $vClient->request('GET', '/status');
        $vClient->takeScreenshot(self::OUTPUT_DIR.'/statut-public.png');

        $vClient->request('GET', '/login');
        $vClient->submitForm('Se connecter', ['_username' => AppFixtures::DEMO_EMAIL, '_password' => AppFixtures::DEMO_PASSWORD]);
        $vClient->waitForElementToContain('h1', 'Moniteurs');
        $vClient->waitForInvisibility('.turbo-progress-bar');
        $vClient->takeScreenshot(self::OUTPUT_DIR.'/tableau-de-bord.png');

        $vClient->request('GET', '/monitors/'.$vRestic->getId());
        $vClient->waitForElementToContain('h1', 'Sauvegarde restic des photos');
        $vClient->takeScreenshot(self::OUTPUT_DIR.'/moniteur.png');

        self::assertFileExists(self::OUTPUT_DIR.'/moniteur.png');
    }
}
