<?php

namespace App\Tests\Command;

use App\Audit\AuditLogger;
use App\Command\EnableTotpCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class TotpEdgeCasesTest extends KernelTestCase
{
    private EntityManagerInterface $vEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
    }

    public function testRotationWithoutTotpAccountOnlyPrintsANewKey(): void
    {
        $vTester = new CommandTester((new Application(static::$kernel))->find('app:totp:rotate-key'));

        $vTester->execute([]);

        $vTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Nouvelle clé', $vTester->getDisplay());
    }

    public function testRotationDeclinedChangesNothing(): void
    {
        $vUser = new User('refus@example.com');
        $vUser->enableTotp('JBSWY3DPEHPK3PXP', static::getContainer()->get(SecretCipher::class)->encrypt('JBSWY3DPEHPK3PXP'));
        $this->vEntityManager->persist($vUser);
        $this->vEntityManager->flush();
        $vTester = new CommandTester((new Application(static::$kernel))->find('app:totp:rotate-key'));

        $vTester->setInputs(['no']);
        $vTester->execute([]);

        self::assertSame(1, $vTester->getStatusCode());
    }

    public function testEnableTotpWithTheRightCodeActivatesAndShowsBackupCodes(): void
    {
        $vUser = new User('actif@example.com');
        $vUser->setPassword('x');
        $this->vEntityManager->persist($vUser);
        $this->vEntityManager->flush();

        $vAuthenticator = $this->createStub(TotpAuthenticatorInterface::class);
        $vAuthenticator->method('generateSecret')->willReturn('JBSWY3DPEHPK3PXP');
        $vAuthenticator->method('getQRContent')->willReturn('otpauth://totp/test');
        $vAuthenticator->method('checkCode')->willReturn(true);
        $vContainer = static::getContainer();
        $vCommand = new EnableTotpCommand(
            $vContainer->get(UserRepository::class),
            $vAuthenticator,
            $this->vEntityManager,
            $vContainer->get(AuditLogger::class),
            $vContainer->get(SecretCipher::class),
        );

        $vInput = new ArrayInput([]);
        $vStream = fopen('php://memory', 'r+');
        fwrite($vStream, "123456\n");
        rewind($vStream);
        $vInput->setStream($vStream);
        $vInput->setInteractive(true);
        $vOutput = new BufferedOutput();

        $vStatus = $vCommand(new SymfonyStyle($vInput, $vOutput), 'actif@example.com');

        self::assertSame(0, $vStatus);
        self::assertStringContainsString('Codes de secours', $vOutput->fetch());
        self::assertSame(10, $vUser->countBackupCodes());
        self::assertTrue($vUser->isTotpAuthenticationEnabled());
    }
}
