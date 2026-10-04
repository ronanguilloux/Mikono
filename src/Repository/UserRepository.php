<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Authored;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * The paginated index builds on this. Until pagination arrived UserController
     * ordered inline with findBy([], ['fullName' => 'ASC']); the ordering lives
     * here now, like every other area's.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.fullName', 'ASC');
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function countReferencingActivities(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.loggedBy = :user')
            ->setParameter('user', $user)
            ->getSingleScalarResult();
    }

    /**
     * Clears every createdBy/updatedBy pointing at $user, so deleting them
     * leaves no dangling id: SQLite runs without foreign keys here, so ON
     * DELETE SET NULL would never fire. Every Authored entity is found from
     * the metadata, so a new one is covered without touching this. ADR 0043.
     */
    public function detachAuthorship(User $user): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!is_subclass_of($metadata->getName(), Authored::class)) {
                continue;
            }

            foreach (['createdBy', 'updatedBy'] as $field) {
                if ($metadata->hasAssociation($field)) {
                    $entityManager
                        ->createQuery(\sprintf('UPDATE %s e SET e.%s = NULL WHERE e.%2$s = :user', $metadata->getName(), $field))
                        ->setParameter('user', $user)
                        ->execute();
                }
            }
        }
    }
}
