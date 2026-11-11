<?php

namespace App\Tests\Command;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateApiTokenCommandTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;
    private CommandTester $vTester;

    protected function setUp(): void
    {
        $vKernel = self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->vEntityManager->createQuery('DELETE FROM '.ApiToken::class)->execute();
        $this->vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $this->vTester = new CommandTester((new Application($vKernel))->find('app:api-token:create'));
    }

    public function testTokenIsCreatedAndDisplayedOnce(): void
    {
        $vUser = new User('api@example.com');
        $vUser->setPassword('x');
        $this->vEntityManager->persist($vUser);
        $this->vEntityManager->flush();

        $this->vTester->execute(['email' => ' API@example.com ', 'name' => 'téléphone']);

        $this->vTester->assertCommandIsSuccessful();
        preg_match('/nm_[0-9a-f]{64}/', $this->vTester->getDisplay(), $vMatches);
        self::assertNotEmpty($vMatches);
        $vHash = (string) $this->vEntityManager->getConnection()->fetchOne('SELECT token_hash FROM api_token');
        self::assertSame(ApiToken::hashToken($vMatches[0]), $vHash);
    }

    public function testUnknownUserIsRefused(): void
    {
        $this->vTester->execute(['email' => 'inconnu@example.com', 'name' => 'x']);

        self::assertSame(1, $this->vTester->getStatusCode());
        self::assertStringContainsString('Aucun utilisateur avec cette adresse.', $this->vTester->getDisplay());
    }
}
