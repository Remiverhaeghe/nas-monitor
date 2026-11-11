<?php

namespace App\Tests;

use App\Kernel;
use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class KernelAndScheduleTest extends TestCase
{
    public function testScheduleDeclaresTheRecurringMessages(): void
    {
        $vSchedule = (new Schedule(new ArrayAdapter()))->getSchedule();

        self::assertCount(4, $vSchedule->getRecurringMessages());
    }

    public function testKernelAllowsTheThreeEnvironments(): void
    {
        $vMethod = new \ReflectionMethod(Kernel::class, 'getAllowedEnvs');

        self::assertSame(['prod', 'dev', 'test'], $vMethod->invoke(new Kernel('test', true)));
    }
}
