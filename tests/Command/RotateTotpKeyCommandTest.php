<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RotateTotpKeyCommandTest extends KernelTestCase
{
    public function testSecretsAreReencryptedWithTheDisplayedKey(): void
    {
        $vKernel = self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $vUser = new User('admin@example.com');
        $vUser->enableTotp('JBSWY3DPEHPK3PXP', static::getContainer()->get(SecretCipher::class)->encrypt('JBSWY3DPEHPK3PXP'));
        $vEntityManager->persist($vUser);
        $vEntityManager->flush();

        $vTester = new CommandTester((new Application($vKernel))->find('app:totp:rotate-key'));
        $vTester->setInputs(['yes']);
        $vTester->execute([]);

        $vTester->assertCommandIsSuccessful();
        preg_match('/^[A-Za-z0-9+\/]{43}=$/m', $vTester->getDisplay(), $vMatches);
        self::assertNotEmpty($vMatches);
        $vStored = (string) $vEntityManager->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$vUser->getId()]);
        self::assertSame('JBSWY3DPEHPK3PXP', (new SecretCipher($vMatches[0]))->decrypt($vStored));
    }
}
