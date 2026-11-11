<?php

namespace App\Tests\EventListener;

use App\Entity\Monitor;
use App\Enum\MonitorType;
use App\EventListener\LiveUpdatePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class LiveUpdatePublisherFailureTest extends KernelTestCase
{
    public function testPublishFailureDoesNotBreakTheFlush(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vBus = $this->createStub(MessageBusInterface::class);
        $vBus->method('dispatch')->willThrowException(new \RuntimeException('Mercure injoignable'));
        $vEntityManager->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], new LiveUpdatePublisher($vBus, new NullLogger()));

        $vEntityManager->persist(new Monitor('Temporaire', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-11-11 12:00:00')));
        $vEntityManager->flush();

        self::assertGreaterThan(0, (int) $vEntityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM monitor'));
    }
}
