<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserBackupCodesTest extends TestCase
{
    public function testCodesMatchWhateverTheDashesSpacesAndCase(): void
    {
        $vUser = new User('a@example.com');
        $vUser->replaceBackupCodes(['AAAA-BBBB', 'ccccdddd']);

        self::assertTrue($vUser->isBackupCode('aaaabbbb'));
        self::assertTrue($vUser->isBackupCode('aaaa bbbb'));
        self::assertTrue($vUser->isBackupCode('CCCC-DDDD'));
        self::assertFalse($vUser->isBackupCode('eeeeffff'));
    }

    public function testInvalidatedCodeLeavesAReindexedList(): void
    {
        $vUser = new User('a@example.com');
        $vUser->replaceBackupCodes(['aaaabbbb', 'ccccdddd', 'eeeeffff']);

        $vUser->invalidateBackupCode('AAAA-BBBB');

        self::assertSame(2, $vUser->countBackupCodes());
        self::assertFalse($vUser->isBackupCode('aaaabbbb'));
        $vHashes = (new \ReflectionProperty(User::class, 'backupCodeHashes'))->getValue($vUser);
        self::assertSame([0, 1], array_keys($vHashes));
    }

    public function testUnreadableTotpSecretFailsLoudlyWithTheEmail(): void
    {
        $vUser = new User('a@example.com');
        $vUser->enableTotp('JBSWY3DPEHPK3PXP', 'v1:chiffre');
        (new \ReflectionProperty(User::class, 'plainTotpSecret'))->setValue($vUser, null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Secret TOTP non déchiffré pour a@example.com');
        $vUser->getTotpAuthenticationConfiguration();
    }
}
