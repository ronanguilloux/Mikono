<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityType>
 */
class ActivityTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityType::class);
    }

    /** @return ActivityType[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(ActivityType $activityType): int
    {
        // TODO(phase 9): drop this guard once App\Entity\Activity exists —
        // until then nothing can reference an ActivityType, so 0 is correct.
        if (!class_exists(\App\Entity\Activity::class)) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM App\Entity\Activity a WHERE a.activityType = :activityType')
            ->setParameter('activityType', $activityType)
            ->getSingleScalarResult();
    }
}
