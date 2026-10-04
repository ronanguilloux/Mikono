<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Authored;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Stamps the signed-in user onto every Authored entity it inserts, and onto
 * an updated one only when its updatedAt moved — i.e. a controller called
 * touch() — so updatedBy and updatedAt always describe the same edit.
 * See ADR 0043.
 *
 * onFlush, not preUpdate: preUpdate can't add a field that isn't already in
 * the change set, and updatedBy usually isn't.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class AuthorStamper
{
    public function __construct(private Security $security) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        // Console commands, fixtures and migrations leave the columns null.
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $stamp = static function (Authored $entity, bool $isNew) use ($user, $entityManager, $unitOfWork): void {
            $entity->recordAuthor($user, $isNew);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        };

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Authored) {
                $stamp($entity, true);
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Authored && isset($unitOfWork->getEntityChangeSet($entity)['updatedAt'])) {
                $stamp($entity, false);
            }
        }
    }
}
