<?php

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;
    private CommandTester $vTester;

    protected function setUp(): void
    {
        $vKernel = self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $this->vTester = new CommandTester((new Application($vKernel))->find('app:user:create'));
    }

    public function testUserIsCreatedWithHashedPassword(): void
    {
        $this->vTester->setInputs(['un-mot-de-passe-solide', 'un-mot-de-passe-solide']);
        $this->vTester->execute(['email' => 'Nouveau@Example.com']);

        $this->vTester->assertCommandIsSuccessful();
        self::assertStringContainsString('nouveau@example.com', $this->vTester->getDisplay());
        $vStored = (string) $this->vEntityManager->getConnection()->fetchOne('SELECT password FROM app_user WHERE email = ?', ['nouveau@example.com']);
        self::assertNotSame('', $vStored);
        self::assertStringNotContainsString('un-mot-de-passe-solide', $vStored);
    }

    public function testInvalidEmailIsRefused(): void
    {
        $this->vTester->execute(['email' => 'pas-un-email']);

        self::assertSame(1, $this->vTester->getStatusCode());
        self::assertStringContainsString('email', $this->vTester->getDisplay());
    }

    public function testTooShortPasswordIsRefused(): void
    {
        $this->vTester->setInputs(['court']);
        $this->vTester->execute(['email' => 'court@example.com']);

        self::assertSame(1, $this->vTester->getStatusCode());
        self::assertStringContainsString('Mot de passe trop court.', $this->vTester->getDisplay());
        self::assertSame(0, (int) $this->vEntityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM app_user'));
    }

    public function testDifferentConfirmationIsRefused(): void
    {
        $this->vTester->setInputs(['un-mot-de-passe-solide', 'une-autre-saisie-longue']);
        $this->vTester->execute(['email' => 'diff@example.com']);

        self::assertSame(1, $this->vTester->getStatusCode());
        self::assertStringContainsString('Les deux saisies sont différentes.', $this->vTester->getDisplay());
    }
}
