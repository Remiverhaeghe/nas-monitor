<?php

namespace App\Tests\Security;

use App\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

class SecretCipherTest extends TestCase
{
    public function testEncryptedSecretIsReadableOnlyWithTheSameKey(): void
    {
        $vCipher = new SecretCipher(SecretCipher::generateKey());
        $vEncrypted = $vCipher->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('v1:', $vEncrypted);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $vEncrypted);
        self::assertNotSame($vEncrypted, $vCipher->encrypt('JBSWY3DPEHPK3PXP'));
        self::assertSame('JBSWY3DPEHPK3PXP', $vCipher->decrypt($vEncrypted));

        $this->expectException(\RuntimeException::class);
        (new SecretCipher(SecretCipher::generateKey()))->decrypt($vEncrypted);
    }

    public function testInvalidKeyIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('32 octets');

        (new SecretCipher(''))->encrypt('secret');
    }

    public function testUnencryptedValueIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SecretCipher(SecretCipher::generateKey()))->decrypt('JBSWY3DPEHPK3PXP');
    }
}
