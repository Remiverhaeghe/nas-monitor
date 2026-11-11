<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserRepositoryTest extends KernelTestCase
{
    public function testPasswordIsRehashedAndSaved(): void
    {
        self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $vUser = new User('hash@example.com');
        $vUser->setPassword('ancien');
        $vEntityManager->persist($vUser);
        $vEntityManager->flush();

        static::getContainer()->get(UserRepository::class)->upgradePassword($vUser, 'nouveau');

        self::assertSame('nouveau', (string) $vEntityManager->getConnection()->fetchOne('SELECT password FROM app_user WHERE email = ?', ['hash@example.com']));
    }

    public function testForeignUserTypeIsRefused(): void
    {
        self::bootKernel();

        $this->expectException(UnsupportedUserException::class);
        static::getContainer()->get(UserRepository::class)->upgradePassword(new InMemoryUser('x', 'y'), 'hash');
    }
}
