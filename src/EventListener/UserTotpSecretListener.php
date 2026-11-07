<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\SecretCipher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * En cas d'échec, le secret reste inconnu et la connexion de ce compte échoue au second facteur.
 */
#[AsEntityListener(event: Events::postLoad, entity: User::class)]
final class UserTotpSecretListener
{
    public function __construct(
        private readonly SecretCipher $aSecretCipher,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function postLoad(User $pUser): void
    {
        $vEncrypted = $pUser->getEncryptedTotpSecret();
        if (null === $vEncrypted) {
            return;
        }

        try {
            $pUser->revealTotpSecret($this->aSecretCipher->decrypt($vEncrypted));
        } catch (\RuntimeException $vException) {
            $this->aLogger->critical('Secret TOTP illisible pour {user} : {message}', [
                'user' => $pUser->getUserIdentifier(),
                'message' => $vException->getMessage(),
            ]);
        }
    }
}
