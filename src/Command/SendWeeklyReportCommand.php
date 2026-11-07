<?php

namespace App\Command;

use App\Report\WeeklyReportSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:report:send', description: 'Envoie tout de suite le rapport de disponibilité des 7 derniers jours')]
final class SendWeeklyReportCommand
{
    public function __construct(
        private readonly WeeklyReportSender $aWeeklyReportSender,
    ) {
    }

    public function __invoke(SymfonyStyle $pIo): int
    {
        $this->aWeeklyReportSender->send();
        $pIo->success('Rapport envoyé à l\'adresse des alertes.');

        return Command::SUCCESS;
    }
}
