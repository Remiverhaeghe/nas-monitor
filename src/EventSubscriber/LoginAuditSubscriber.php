<?php

namespace App\EventSubscriber;

use App\Audit\AuditLogger;
use App\Enum\AuditAction;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Seul le pare-feu de l'interface est tracé : l'API s'authentifie à chaque appel.
 */
final class LoginAuditSubscriber implements EventSubscriberInterface
{
    private const FIREWALL = 'main';

    public function __construct(
        private readonly AuditLogger $aAuditLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $pEvent): void
    {
        // Avec la double authentification, la connexion n'est acquise qu'après le code
        if (self::FIREWALL !== $pEvent->getFirewallName() || $pEvent->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            return;
        }

        $this->aAuditLogger->log(AuditAction::Login, 'Connexion réussie', $pEvent->getUser()->getUserIdentifier());
    }

    public function onLoginFailure(LoginFailureEvent $pEvent): void
    {
        if (self::FIREWALL !== $pEvent->getFirewallName()) {
            return;
        }

        $vBadge = $pEvent->getPassport()?->hasBadge(UserBadge::class) ? $pEvent->getPassport()->getBadge(UserBadge::class) : null;
        $vActor = $vBadge instanceof UserBadge ? $vBadge->getUserIdentifier() : null;

        $this->aAuditLogger->log(AuditAction::LoginFailure, $pEvent->getException()->getMessageKey(), $vActor);
    }
}
