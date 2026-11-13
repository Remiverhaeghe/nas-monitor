<?php

namespace App\Tests\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Security\ApiTokenHandler;
use App\Tests\Support\RecordingLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiTokenLoggingTest extends TestCase
{
    public function testUnknownTokenIsLoggedAsAWarning(): void
    {
        $vRepository = $this->createStub(ApiTokenRepository::class);
        $vRepository->method('findOneByToken')->willReturn(null);
        $vLogger = new RecordingLogger();
        $vHandler = new ApiTokenHandler($vRepository, $this->createStub(EntityManagerInterface::class), new MockClock('2026-11-11 12:00:00'), $vLogger);

        try {
            $vHandler->getUserBadgeFrom('nm_inconnu');
            self::fail('Le jeton inconnu aurait dû être refusé.');
        } catch (BadCredentialsException) {
            self::assertSame([['level' => 'warning', 'message' => 'Appel API avec un jeton inconnu', 'context' => []]], $vLogger->records);
        }
    }

    public function testLastUsageIsNotRewrittenAtExactlyFiveMinutes(): void
    {
        $vClock = new MockClock('2026-11-11 12:00:00');
        $vApiToken = new ApiToken(new User('api@example.com'), 'tel', 'hash', $vClock->now());
        $vRepository = $this->createStub(ApiTokenRepository::class);
        $vRepository->method('findOneByToken')->willReturn($vApiToken);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::exactly(2))->method('flush');
        $vHandler = new ApiTokenHandler($vRepository, $vEntityManager, $vClock, new RecordingLogger());

        $vHandler->getUserBadgeFrom('nm_a');
        $vClock->sleep(300);
        $vHandler->getUserBadgeFrom('nm_a');
        self::assertSame('12:00:00', $vApiToken->getLastUsedAt()->format('H:i:s'));
        $vClock->sleep(1);
        $vHandler->getUserBadgeFrom('nm_a');
    }
}
