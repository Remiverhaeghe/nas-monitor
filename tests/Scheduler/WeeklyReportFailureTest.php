<?php

namespace App\Tests\Scheduler;

use App\Report\WeeklyReportSender;
use App\Repository\IncidentRepository;
use App\Repository\MonitorRepository;
use App\Repository\PingRepository;
use App\Scheduler\WeeklyReportHandler;
use App\Scheduler\WeeklyReportMessage;
use App\Service\AvailabilityCalculator;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class WeeklyReportFailureTest extends KernelTestCase
{
    public function testMailFailureIsLoggedWithoutThrowing(): void
    {
        self::bootKernel();
        $vContainer = static::getContainer();
        $vMailer = $this->createStub(MailerInterface::class);
        $vMailer->method('send')->willThrowException(new TransportException('SMTP indisponible'));
        $vSender = new WeeklyReportSender(
            $vContainer->get(MonitorRepository::class),
            $vContainer->get(IncidentRepository::class),
            $vContainer->get(PingRepository::class),
            $vContainer->get(AvailabilityCalculator::class),
            $vMailer,
            new MockClock('2026-11-11 12:00:00'),
            new NullLogger(),
            'nas-monitor@localhost',
            'admin@localhost',
            'Europe/Paris',
        );

        (new WeeklyReportHandler($vSender, new NullLogger()))(new WeeklyReportMessage());

        $this->addToAssertionCount(1);
    }
}
