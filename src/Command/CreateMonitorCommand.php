<?php

namespace App\Command;

use App\Entity\Monitor;
use App\Enum\MonitorType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:monitor:create', description: 'Crée un moniteur de type heartbeat et affiche son jeton de ping')]
final class CreateMonitorCommand
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ValidatorInterface $aValidator,
        private readonly ClockInterface $aClock,
        private readonly UrlGeneratorInterface $aUrlGenerator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $pIo,
        #[Argument(description: 'Nom du moniteur', name: 'name')]
        string $pName,
        #[Option(description: 'Intervalle attendu entre deux pings, en secondes', name: 'interval')]
        int $pInterval = 3600,
        #[Option(description: 'Délai de grâce avant de passer en panne, en secondes', name: 'grace')]
        int $pGrace = 600,
    ): int {
        $vMonitor = new Monitor($pName, MonitorType::Heartbeat, $pInterval, $pGrace, $this->aClock->now());

        $vViolations = $this->aValidator->validate($vMonitor);
        if (\count($vViolations) > 0) {
            foreach ($vViolations as $vViolation) {
                $pIo->error($vViolation->getPropertyPath().' : '.$vViolation->getMessage());
            }

            return Command::FAILURE;
        }

        $vToken = $vMonitor->rotatePingToken();
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->flush();

        $vUrl = $this->aUrlGenerator->generate('ping', ['token' => $vToken], UrlGeneratorInterface::ABSOLUTE_URL);
        $pIo->success(\sprintf('Moniteur « %s » créé (id %d).', $vMonitor->getName(), $vMonitor->getId()));
        $pIo->writeln('Jeton, affiché une seule fois : '.$vToken);
        $pIo->writeln('Exemple : curl -fsS -m 10 --retry 3 -X POST '.$vUrl);

        return Command::SUCCESS;
    }
}
