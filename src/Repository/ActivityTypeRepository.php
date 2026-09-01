<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC');
    }

    /** @return ActivityType[] */
    public function findAllOrderedByName(): array
    {
        return $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(ActivityType $activityType): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.activityType = :activityType')
            ->setParameter('activityType', $activityType)
            ->getSingleScalarResult();
    }
}
