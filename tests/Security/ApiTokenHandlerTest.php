<?php

namespace App\Tests\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Security\ApiTokenHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiTokenHandlerTest extends TestCase
{
    public function testUnknownTokenIsRefused(): void
    {
        $vRepository = $this->createStub(ApiTokenRepository::class);
        $vRepository->method('findOneByToken')->willReturn(null);
        $vHandler = new ApiTokenHandler($vRepository, $this->createStub(EntityManagerInterface::class), new MockClock('2026-11-11 12:00:00'), new NullLogger());

        $this->expectException(BadCredentialsException::class);
        $vHandler->getUserBadgeFrom('nm_inconnu');
    }

    public function testLastUsageIsRecordedOnlyOncePerFiveMinutes(): void
    {
        $vClock = new MockClock('2026-11-11 12:00:00');
        $vApiToken = new ApiToken(new User('api@example.com'), 'tel', 'hash', $vClock->now());
        $vRepository = $this->createStub(ApiTokenRepository::class);
        $vRepository->method('findOneByToken')->willReturn($vApiToken);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::exactly(2))->method('flush');
        $vHandler = new ApiTokenHandler($vRepository, $vEntityManager, $vClock, new NullLogger());

        $vBadge = $vHandler->getUserBadgeFrom('nm_a');
        self::assertSame('api@example.com', $vBadge->getUserIdentifier());
        $vClock->sleep(60);
        $vHandler->getUserBadgeFrom('nm_a');
        $vClock->sleep(400);
        $vHandler->getUserBadgeFrom('nm_a');

        self::assertSame('2026-11-11 12:07:40', $vApiToken->getLastUsedAt()->format('Y-m-d H:i:s'));
    }
}
