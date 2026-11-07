<?php

namespace App\Scheduler;

use App\Report\WeeklyReportSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WeeklyReportHandler
{
    public function __construct(
        private readonly WeeklyReportSender $aWeeklyReportSender,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function __invoke(WeeklyReportMessage $pMessage): void
    {
        try {
            $this->aWeeklyReportSender->send();
        } catch (\Throwable $vException) {
            $this->aLogger->error('Échec du rapport hebdomadaire : {message}', ['message' => $vException->getMessage()]);
        }
    }
}
