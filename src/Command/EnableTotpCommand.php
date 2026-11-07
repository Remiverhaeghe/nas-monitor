<?php

namespace App\Command;

use App\Repository\UserRepository;
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
        $vUser->setTotpSecret($vSecret);

        $pIo->writeln('Ajoute ce compte dans ton application d\'authentification (saisie manuelle) :');
        $pIo->writeln('  Clé : '.$vSecret);
        $pIo->writeln('  Ou lien otpauth : '.$this->aTotpAuthenticator->getQRContent($vUser));

        $vCode = (string) $pIo->ask('Code à six chiffres affiché par l\'application');
        if (!$this->aTotpAuthenticator->checkCode($vUser, $vCode)) {
            $pIo->error('Code incorrect : la double authentification n\'est pas activée.');

            return Command::FAILURE;
        }

        $this->aEntityManager->flush();
        $pIo->success('Double authentification activée pour '.$vUser->getEmail().'.');

        return Command::SUCCESS;
    }
}
