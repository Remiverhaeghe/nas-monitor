<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TotpCommandsTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;
    private Application $vApplication;

    protected function setUp(): void
    {
        $vKernel = self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $this->vApplication = new Application($vKernel);
    }

    private function persistTestUser(bool $pWithTotp): User
    {
        $vUser = new User('totp@example.com');
        $vUser->setPassword('x');
        if ($pWithTotp) {
            $vUser->enableTotp('JBSWY3DPEHPK3PXP', static::getContainer()->get(SecretCipher::class)->encrypt('JBSWY3DPEHPK3PXP'));
        }
        $this->vEntityManager->persist($vUser);
        $this->vEntityManager->flush();

        return $vUser;
    }

    /**
     * @param array<string, string> $pInput
     * @param list<string>          $pAnswers
     */
    private function executeAppCommand(string $pCommand, array $pInput, array $pAnswers = []): CommandTester
    {
        $vTester = new CommandTester($this->vApplication->find($pCommand));
        $vTester->setInputs($pAnswers);
        $vTester->execute($pInput);

        return $vTester;
    }

    public function testDisableTotp(): void
    {
        $this->persistTestUser(true);

        $vTester = $this->executeAppCommand('app:user:totp-disable', ['email' => 'TOTP@example.com']);

        $vTester->assertCommandIsSuccessful();
        self::assertNull($this->vEntityManager->getConnection()->fetchOne('SELECT totp_secret FROM app_user') ?: null);
    }

    public function testBackupCodesAreRegenerated(): void
    {
        $this->persistTestUser(true);

        $vTester = $this->executeAppCommand('app:user:backup-codes', ['email' => 'totp@example.com']);

        $vTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Nouveaux codes', $vTester->getDisplay());
    }

    public function testBackupCodesRefusedWithoutTotp(): void
    {
        $this->persistTestUser(false);

        $vTester = $this->executeAppCommand('app:user:backup-codes', ['email' => 'totp@example.com']);

        self::assertSame(1, $vTester->getStatusCode());
        self::assertStringContainsString('pas activée', $vTester->getDisplay());
    }

    public function testUnknownUserIsRefusedByEveryCommand(): void
    {
        foreach (['app:user:totp-disable', 'app:user:backup-codes', 'app:user:totp-enable'] as $vName) {
            $vTester = $this->executeAppCommand($vName, ['email' => 'inconnu@example.com']);

            self::assertSame(1, $vTester->getStatusCode(), $vName);
            self::assertStringContainsString('Aucun utilisateur avec cette adresse.', $vTester->getDisplay(), $vName);
        }
    }

    public function testEnableTotpWithWrongCodeLeavesItDisabled(): void
    {
        $this->persistTestUser(false);

        $vTester = $this->executeAppCommand('app:user:totp-enable', ['email' => 'totp@example.com'], ['abcdef']);

        self::assertSame(1, $vTester->getStatusCode());
        self::assertStringContainsString('Code incorrect', $vTester->getDisplay());
        self::assertStringContainsString('Clé :', $vTester->getDisplay());
    }
}
