<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use App\Security\BackupCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:backup-codes', description: 'Remplace les codes de secours de la double authentification')]
final class RegenerateBackupCodesCommand
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
        if (!$vUser->isTotpAuthenticationEnabled()) {
            $pIo->error('La double authentification n\'est pas activée pour ce compte.');

            return Command::FAILURE;
        }

        $vBackupCodes = BackupCodeGenerator::generate();
        $vUser->replaceBackupCodes($vBackupCodes);
        $this->aEntityManager->flush();
        $this->aAuditLogger->log(AuditAction::BackupCodesGenerate, 'Compte '.$vUser->getEmail());

        $pIo->success('Les anciens codes de secours ne fonctionnent plus.');
        $pIo->writeln('Nouveaux codes, à usage unique, à conserver hors du téléphone :');
        $pIo->listing($vBackupCodes);

        return Command::SUCCESS;
    }
}
