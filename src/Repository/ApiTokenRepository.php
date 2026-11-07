<?php

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, ApiToken::class);
    }

    public function findOneByToken(string $pToken): ?ApiToken
    {
        $rApiToken = $this->findOneBy(['tokenHash' => ApiToken::hashToken($pToken)]);

        return $rApiToken;
    }
}
