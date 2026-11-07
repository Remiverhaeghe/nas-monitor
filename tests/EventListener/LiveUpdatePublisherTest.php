<?php

namespace App\Tests\EventListener;

use App\Audit\AuditLogger;
use App\Entity\Incident;
use App\Entity\Monitor;
use App\Entity\Ping;
use App\Enum\AuditAction;
use App\Enum\MonitorType;
use App\Enum\PingKind;
use App\EventListener\LiveUpdatePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class LiveUpdatePublisherTest extends KernelTestCase
{
    private EntityManagerInterface $aEntityManager;
    private InMemoryTransport $aTransport;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->aEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([Incident::class, Ping::class, Monitor::class] as $vEntityClass) {
            $this->aEntityManager->createQuery('DELETE FROM '.$vEntityClass)->execute();
        }
        $vTransport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $vTransport);
        $this->aTransport = $vTransport;
    }

    public function testMonitorChangePublishesOnePrivateRefresh(): void
    {
        $vMonitor = new Monitor('Sauvegarde restic', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable());
        $this->aEntityManager->persist($vMonitor);
        $this->aEntityManager->persist(new Ping($vMonitor, PingKind::Start, new \DateTimeImmutable()));
        $this->aEntityManager->flush();

        $vUpdates = $this->sentUpdates();
        self::assertCount(1, $vUpdates);
        self::assertSame([LiveUpdatePublisher::TOPIC], $vUpdates[0]->getTopics());
        self::assertTrue($vUpdates[0]->isPrivate());
        self::assertStringContainsString('action="refresh"', $vUpdates[0]->getData());
    }

    public function testAuditEntryPublishesNothing(): void
    {
        static::getContainer()->get(AuditLogger::class)->log(AuditAction::UserCreate, 'Compte test');

        self::assertSame([], $this->sentUpdates());
    }

    /**
     * @return list<Update>
     */
    private function sentUpdates(): array
    {
        $rUpdates = [];
        foreach ($this->aTransport->getSent() as $vEnvelope) {
            if ($vEnvelope->getMessage() instanceof Update) {
                $rUpdates[] = $vEnvelope->getMessage();
            }
        }

        return $rUpdates;
    }
}
