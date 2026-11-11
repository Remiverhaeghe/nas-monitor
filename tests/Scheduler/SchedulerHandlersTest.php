<?php

namespace App\Tests\Scheduler;

use App\Check\ActiveChecker;
use App\Check\ActiveCheckRunner;
use App\Repository\MonitorRepository;
use App\Scheduler\CheckMonitorsHandler;
use App\Scheduler\CheckMonitorsMessage;
use App\Scheduler\WeeklyReportHandler;
use App\Scheduler\WeeklyReportMessage;
use App\Service\IncidentReminder;
use App\Service\MonitorChecker;
use App\Service\PingRecorder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

class SchedulerHandlersTest extends KernelTestCase
{
    public function testCheckMonitorsRunsAllThreeSteps(): void
    {
        self::bootKernel();

        static::getContainer()->get(CheckMonitorsHandler::class)(new CheckMonitorsMessage());

        $this->addToAssertionCount(1);
    }

    public function testFailingStepIsLoggedAndTheOthersStillRun(): void
    {
        self::bootKernel();
        $vContainer = static::getContainer();
        $vRepository = $this->createStub(MonitorRepository::class);
        $vRepository->method('findActiveChecks')->willThrowException(new \RuntimeException('base coupée'));
        $vRunner = new ActiveCheckRunner(
            $vRepository,
            $vContainer->get(ActiveChecker::class),
            $vContainer->get(PingRecorder::class),
            new MockClock('2026-11-11 12:00:00'),
            new NullLogger(),
        );
        $vHandler = new CheckMonitorsHandler(
            $vRunner,
            $vContainer->get(MonitorChecker::class),
            $vContainer->get(IncidentReminder::class),
            $vContainer->get(ManagerRegistry::class),
            new NullLogger(),
        );

        $vHandler(new CheckMonitorsMessage());

        $this->addToAssertionCount(1);
    }

    public function testWeeklyReportHandlerSendsTheReport(): void
    {
        self::bootKernel();

        static::getContainer()->get(WeeklyReportHandler::class)(new WeeklyReportMessage());

        $this->addToAssertionCount(1);
    }

    public function testReportCommandConfirmsTheSending(): void
    {
        $vKernel = self::bootKernel();
        $vTester = new CommandTester((new Application($vKernel))->find('app:report:send'));

        $vTester->execute([]);

        $vTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Rapport envoyé', $vTester->getDisplay());
    }
}
