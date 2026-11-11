<?php

namespace App\Tests\Audit;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLoggerFailureTest extends TestCase
{
    public function testWriteFailureDoesNotInterruptTheAction(): void
    {
        $vEntityManager = $this->createStub(EntityManagerInterface::class);
        $vEntityManager->method('persist')->willThrowException(new \RuntimeException('base coupée'));
        $vLogger = new AuditLogger($vEntityManager, $this->createStub(Security::class), new RequestStack(), new MockClock('2026-11-11 12:00:00'), new NullLogger());

        $vLogger->log(AuditAction::Login, 'Connexion');

        $this->addToAssertionCount(1);
    }
}
