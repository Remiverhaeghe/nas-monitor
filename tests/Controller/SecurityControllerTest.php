<?php

namespace App\Tests\Controller;

use App\Controller\SecurityController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testAuthenticatedUserIsRedirectedAwayFromTheLoginPage(): void
    {
        $vClient = static::createClient();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vEntityManager->createQuery('DELETE FROM '.User::class)->execute();
        $vUser = new User('connecte@example.com');
        $vUser->setPassword('x');
        $vEntityManager->persist($vUser);
        $vEntityManager->flush();

        $vClient->loginUser($vUser);
        $vClient->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    public function testLogoutRouteIsHandledByTheFirewall(): void
    {
        $this->expectException(\LogicException::class);
        (new SecurityController())->logout();
    }
}
