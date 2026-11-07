<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, User::class);
    }

    /**
     * Rehache le mot de passe à la connexion quand l'algorithme ou son coût a changé.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $pUser, string $pNewHashedPassword): void
    {
        if (!$pUser instanceof User) {
            throw new UnsupportedUserException(\sprintf('Utilisateur de type %s non pris en charge.', $pUser::class));
        }

        $pUser->setPassword($pNewHashedPassword);
        $this->getEntityManager()->flush();
    }
}
