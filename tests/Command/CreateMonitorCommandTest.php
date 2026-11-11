<?php

namespace App\Tests\Command;

use App\Entity\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateMonitorCommandTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;
    private CommandTester $vTester;

    protected function setUp(): void
    {
        $vKernel = self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->vEntityManager->createQuery('DELETE FROM '.Monitor::class)->execute();
        $this->vTester = new CommandTester((new Application($vKernel))->find('app:monitor:create'));
    }

    public function testHeartbeatMonitorIsCreatedWithItsPingUrl(): void
    {
        $this->vTester->execute(['name' => 'Sauvegarde nocturne', '--interval' => 7200, '--grace' => 300]);

        $this->vTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Sauvegarde nocturne', $this->vTester->getDisplay());
        self::assertStringContainsString('curl', $this->vTester->getDisplay());
        self::assertSame(1, (int) $this->vEntityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM monitor'));
    }

    public function testInvalidIntervalIsRefused(): void
    {
        $this->vTester->execute(['name' => 'Trop rapide', '--interval' => 5]);

        self::assertSame(1, $this->vTester->getStatusCode());
        self::assertSame(0, (int) $this->vEntityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM monitor'));
    }
}
