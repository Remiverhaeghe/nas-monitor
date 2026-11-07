<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:totp-disable', description: 'Désactive la double authentification, par exemple après la perte du téléphone')]
final class DisableTotpCommand
{
    public function __construct(
        private readonly UserRepository $aUserRepository,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly AuditLogger $aAuditLogger,
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

        $vUser->setTotpSecret(null);
        $this->aEntityManager->flush();
        $this->aAuditLogger->log(AuditAction::TotpDisable, 'Compte '.$vUser->getEmail());
        $pIo->success('Double authentification désactivée pour '.$vUser->getEmail().'.');

        return Command::SUCCESS;
    }
}
