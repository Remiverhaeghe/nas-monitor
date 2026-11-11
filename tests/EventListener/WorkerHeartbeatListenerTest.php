<?php

namespace App\Tests\EventListener;

use App\EventListener\WorkerHeartbeatListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Worker;

class WorkerHeartbeatListenerTest extends TestCase
{
    private string $aFile;

    protected function setUp(): void
    {
        $this->aFile = sys_get_temp_dir().'/worker-heartbeat-test';
        @unlink($this->aFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->aFile);
    }

    public function testFileIsTouchedAtMostEveryThirtySeconds(): void
    {
        $vListener = new WorkerHeartbeatListener($this->aFile, new NullLogger());
        $vEvent = new WorkerRunningEvent(new Worker([], new MessageBus()), true);

        $vListener($vEvent);
        self::assertFileExists($this->aFile);

        touch($this->aFile, 1000);
        clearstatcache();
        $vListener($vEvent);
        clearstatcache();
        self::assertSame(1000, filemtime($this->aFile));
    }

    public function testUnwritableFileOnlyLogsAWarning(): void
    {
        $vListener = new WorkerHeartbeatListener('/dossier/inexistant/worker-heartbeat', new NullLogger());

        $vListener(new WorkerRunningEvent(new Worker([], new MessageBus()), true));

        $this->addToAssertionCount(1);
    }
}
