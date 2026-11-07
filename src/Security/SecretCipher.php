<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Chiffrement authentifié (libsodium secretbox) des secrets stockés en base.
 * La clé vient de l'environnement : une copie de la base seule ne suffit pas à lire les secrets.
 */
final class SecretCipher
{
    private const PREFIX = 'v1:';

    public function __construct(
        #[Autowire(env: 'TOTP_ENCRYPTION_KEY')]
        private readonly string $aEncodedKey,
    ) {
    }

    public static function generateKey(): string
    {
        $rKey = base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        return $rKey;
    }

    public function encrypt(string $pPlain): string
    {
        $vNonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $rEncrypted = self::PREFIX.base64_encode($vNonce.sodium_crypto_secretbox($pPlain, $vNonce, $this->key()));

        return $rEncrypted;
    }

    public function decrypt(string $pEncrypted): string
    {
        $vDecoded = str_starts_with($pEncrypted, self::PREFIX)
            ? base64_decode(substr($pEncrypted, \strlen(self::PREFIX)), true)
            : false;
        if (false === $vDecoded || \strlen($vDecoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Secret chiffré au format inattendu');
        }

        $rPlain = sodium_crypto_secretbox_open(
            substr($vDecoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($vDecoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key(),
        );
        if (false === $rPlain) {
            throw new \RuntimeException('Déchiffrement impossible : clé TOTP_ENCRYPTION_KEY différente de celle du chiffrement');
        }

        return $rPlain;
    }

    private function key(): string
    {
        $rKey = base64_decode($this->aEncodedKey, true);
        if (false === $rKey || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($rKey)) {
            throw new \RuntimeException('TOTP_ENCRYPTION_KEY doit contenir 32 octets encodés en base64');
        }

        return $rKey;
    }
}
