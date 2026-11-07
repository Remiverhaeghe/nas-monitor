<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use App\Security\BackupCodeGenerator;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Le code saisi en fin de commande confirme que l'application est bien configurée avant d'activer la protection.
 */
#[AsCommand(name: 'app:user:totp-enable', description: 'Active la double authentification par code TOTP pour un compte')]
final class EnableTotpCommand
{
    public function __construct(
        private readonly UserRepository $aUserRepository,
        private readonly TotpAuthenticatorInterface $aTotpAuthenticator,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly AuditLogger $aAuditLogger,
        private readonly SecretCipher $aSecretCipher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $pIo,
        #[Argument(description: 'Adresse e-mail du compte', name: 'email')]
        string $pEmail,
    ): int {
        $vUser = $this->aUserRepository->findOneBy(['email' => mb_strtolower(trim($pEmail))]);
        if (null === $vUser) {
            $pIo->error('Aucun utilisateur avec cette adresse.');

            return Command::FAILURE;
        }

        $vSecret = $this->aTotpAuthenticator->generateSecret();
        $vUser->enableTotp($vSecret, $this->aSecretCipher->encrypt($vSecret));

        $pIo->writeln('Ajoute ce compte dans ton application d\'authentification (saisie manuelle) :');
        $pIo->writeln('  Clé : '.$vSecret);
        $pIo->writeln('  Ou lien otpauth : '.$this->aTotpAuthenticator->getQRContent($vUser));

        $vCode = (string) $pIo->ask('Code à six chiffres affiché par l\'application');
        if (!$this->aTotpAuthenticator->checkCode($vUser, $vCode)) {
            $pIo->error('Code incorrect : la double authentification n\'est pas activée.');

            return Command::FAILURE;
        }

        $vBackupCodes = BackupCodeGenerator::generate();
        $vUser->replaceBackupCodes($vBackupCodes);
        $this->aEntityManager->flush();
        $this->aAuditLogger->log(AuditAction::TotpEnable, 'Compte '.$vUser->getEmail());
        $pIo->success('Double authentification activée pour '.$vUser->getEmail().'.');
        $pIo->writeln('Codes de secours, à usage unique, à conserver hors du téléphone (ils ne seront plus affichés) :');
        $pIo->listing($vBackupCodes);

        return Command::SUCCESS;
    }
}
