<?php

namespace App\Command;

use App\Entity\ApiToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:api-token:create', description: 'Crée un jeton d\'accès à l\'API pour un utilisateur')]
final class CreateApiTokenCommand
{
    public function __construct(
        private readonly UserRepository $aUserRepository,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $pIo,
        #[Argument(description: 'Adresse e-mail de l\'utilisateur', name: 'email')]
        string $pEmail,
        #[Argument(description: 'Nom du jeton, par exemple « téléphone »', name: 'name')]
        string $pName,
    ): int {
        $vUser = $this->aUserRepository->findOneBy(['email' => mb_strtolower(trim($pEmail))]);
        if (null === $vUser) {
            $pIo->error('Aucun utilisateur avec cette adresse.');

            return Command::FAILURE;
        }

        [$vToken, $vApiToken] = ApiToken::generate($vUser, mb_substr($pName, 0, 100), $this->aClock->now());
        $this->aEntityManager->persist($vApiToken);
        $this->aEntityManager->flush();

        $pIo->success(\sprintf('Jeton « %s » créé.', $vApiToken->getName()));
        $pIo->writeln('Jeton, affiché une seule fois : '.$vToken);
        $pIo->writeln('Utilisation : en-tête Authorization: Bearer <jeton>');

        return Command::SUCCESS;
    }
}
