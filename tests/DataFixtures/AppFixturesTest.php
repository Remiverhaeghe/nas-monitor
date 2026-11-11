<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AppFixturesTest extends KernelTestCase
{
    public function testDemoDataIsLoaded(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, MaintenanceWindow::class, Monitor::class, User::class] as $vClass) {
            $vEntityManager->createQuery('DELETE FROM '.$vClass)->execute();
        }

        static::getContainer()->get(AppFixtures::class)->load($vEntityManager);
        $vEntityManager->flush();

        $vConnection = $vEntityManager->getConnection();
        self::assertSame(1, (int) $vConnection->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertGreaterThan(5, (int) $vConnection->fetchOne('SELECT COUNT(*) FROM monitor'));
        self::assertGreaterThan(100, (int) $vConnection->fetchOne('SELECT COUNT(*) FROM ping'));
    }
}
