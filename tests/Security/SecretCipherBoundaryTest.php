<?php

namespace App\Tests\Security;

use App\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

class SecretCipherBoundaryTest extends TestCase
{
    public function testPayloadWithoutCipherTextIsRejectedAsMalformed(): void
    {
        $vCipher = new SecretCipher(SecretCipher::generateKey());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Secret chiffré au format inattendu');
        $vCipher->decrypt('v1:'.base64_encode(str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)));
    }

    public function testPayloadWithoutPrefixIsRejectedAsMalformed(): void
    {
        $vCipher = new SecretCipher(SecretCipher::generateKey());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Secret chiffré au format inattendu');
        $vCipher->decrypt(base64_encode(random_bytes(64)));
    }
}
