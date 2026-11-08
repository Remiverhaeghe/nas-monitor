<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Les secrets sont lus avec la clé actuelle, puis rechiffrés avec une nouvelle clé dans une seule transaction.
 * Tant que TOTP_ENCRYPTION_KEY n'est pas remplacée et l'application redémarrée, la double authentification échoue.
 */
#[AsCommand(name: 'app:totp:rotate-key', description: 'Rechiffre les secrets TOTP avec une nouvelle clé et l\'affiche')]
final class RotateTotpKeyCommand
{
    public function __construct(
        private readonly UserRepository $aUserRepository,
        private readonly SecretCipher $aSecretCipher,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly AuditLogger $aAuditLogger,
    ) {
    }

    public function __invoke(SymfonyStyle $pIo): int
    {
        $vUsers = array_filter($this->aUserRepository->findAll(), static fn ($pUser): bool => $pUser->isTotpAuthenticationEnabled());
        if ([] === $vUsers) {
            $pIo->note('Aucun compte n\'utilise la double authentification : remplace simplement la clé.');
            $pIo->writeln('Nouvelle clé : '.SecretCipher::generateKey());

            return Command::SUCCESS;
        }

        if (!$pIo->confirm(\sprintf('Rechiffrer les secrets de %d compte(s) ? La connexion échouera jusqu\'au remplacement de la clé.', \count($vUsers)), false)) {
            return Command::FAILURE;
        }

        $vNewKey = SecretCipher::generateKey();
        $vNewCipher = new SecretCipher($vNewKey);
        $this->aEntityManager->wrapInTransaction(function () use ($vUsers, $vNewCipher): void {
            foreach ($vUsers as $vUser) {
                $vPlain = $this->aSecretCipher->decrypt((string) $vUser->getEncryptedTotpSecret());
                $vUser->enableTotp($vPlain, $vNewCipher->encrypt($vPlain));
            }
        });
        $this->aAuditLogger->log(AuditAction::TotpKeyRotate, \sprintf('%d secret(s) rechiffré(s)', \count($vUsers)));

        $pIo->success('Secrets rechiffrés.');
        $pIo->writeln('Remplace maintenant TOTP_ENCRYPTION_KEY par cette valeur, puis redémarre l\'application :');
        $pIo->writeln($vNewKey);

        return Command::SUCCESS;
    }
}
