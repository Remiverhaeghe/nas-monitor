<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Entity\User;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un compte pour accéder au tableau de bord')]
final class CreateUserCommand
{
    private const PASSWORD_MIN_LENGTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly UserPasswordHasherInterface $aPasswordHasher,
        private readonly ValidatorInterface $aValidator,
        private readonly AuditLogger $aAuditLogger,
    ) {
    }

    public function __invoke(
        SymfonyStyle $pIo,
        #[Argument(description: 'Adresse e-mail servant d\'identifiant', name: 'email')]
        string $pEmail,
    ): int {
        $vUser = new User($pEmail);

        $vViolations = $this->aValidator->validate($vUser);
        if (\count($vViolations) > 0) {
            foreach ($vViolations as $vViolation) {
                $pIo->error($vViolation->getPropertyPath().' : '.$vViolation->getMessage());
            }

            return Command::FAILURE;
        }

        $vPassword = (string) $pIo->askHidden('Mot de passe ('.self::PASSWORD_MIN_LENGTH.' caractères minimum)');
        if (mb_strlen($vPassword) < self::PASSWORD_MIN_LENGTH) {
            $pIo->error('Mot de passe trop court.');

            return Command::FAILURE;
        }
        if ($vPassword !== $pIo->askHidden('Confirmation')) {
            $pIo->error('Les deux saisies sont différentes.');

            return Command::FAILURE;
        }

        $vUser->setPassword($this->aPasswordHasher->hashPassword($vUser, $vPassword));
        $this->aEntityManager->persist($vUser);
        $this->aEntityManager->flush();

        $this->aAuditLogger->log(AuditAction::UserCreate, 'Compte '.$vUser->getEmail());
        $pIo->success(\sprintf('Compte %s créé.', $vUser->getEmail()));

        return Command::SUCCESS;
    }
}
