<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ApiTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * Évite une écriture en base à chaque appel de l'application mobile.
     */
    private const LAST_USED_PRECISION_SECONDS = 300;

    public function __construct(
        private readonly ApiTokenRepository $aApiTokenRepository,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $pAccessToken): UserBadge
    {
        $vApiToken = $this->aApiTokenRepository->findOneByToken($pAccessToken);
        if (null === $vApiToken) {
            $this->aLogger->warning('Appel API avec un jeton inconnu');

            throw new BadCredentialsException('Jeton invalide.');
        }

        $vNow = $this->aClock->now();
        $vLastUsedAt = $vApiToken->getLastUsedAt();
        if (null === $vLastUsedAt || $vNow->getTimestamp() - $vLastUsedAt->getTimestamp() > self::LAST_USED_PRECISION_SECONDS) {
            $vApiToken->markUsed($vNow);
            $this->aEntityManager->flush();
        }

        $rBadge = new UserBadge($vApiToken->getUser()->getUserIdentifier());

        return $rBadge;
    }
}
