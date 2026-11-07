<?php

namespace App\EventListener;

use App\Entity\Incident;
use App\Entity\MaintenanceWindow;
use App\Entity\Monitor;
use App\Entity\Ping;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Demande aux pages ouvertes de se recharger quand un moniteur, un ping, un incident ou une maintenance change.
 * La mise à jour ne contient aucune donnée : la page relit tout avec les droits de l'utilisateur connecté.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class LiveUpdatePublisher
{
    public const TOPIC = 'monitors';

    private const WATCHED_CLASSES = [Monitor::class, Ping::class, Incident::class, MaintenanceWindow::class];

    private bool $aChanged = false;

    public function __construct(
        private readonly MessageBusInterface $aMessageBus,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function onFlush(OnFlushEventArgs $pArgs): void
    {
        $vUnitOfWork = $pArgs->getObjectManager()->getUnitOfWork();
        $vEntities = [
            ...$vUnitOfWork->getScheduledEntityInsertions(),
            ...$vUnitOfWork->getScheduledEntityUpdates(),
            ...$vUnitOfWork->getScheduledEntityDeletions(),
        ];

        foreach ($vEntities as $vEntity) {
            if (\in_array($vEntity::class, self::WATCHED_CLASSES, true)) {
                $this->aChanged = true;

                return;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $pArgs): void
    {
        if (!$this->aChanged) {
            return;
        }
        $this->aChanged = false;

        try {
            $this->aMessageBus->dispatch(new Update(self::TOPIC, '<turbo-stream action="refresh"></turbo-stream>', true));
        } catch (\Throwable $vException) {
            $this->aLogger->warning('Mise à jour en direct non envoyée : {message}', ['message' => $vException->getMessage()]);
        }
    }
}
