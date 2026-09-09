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
     * The one ordered-by-name query: the paginated index builds on it, and both
     * activity forms hand it straight to their activity-type picker.
     *
     * No findAllOrderedByName() twin here, unlike VolunteerRepository and
     * ProjectRepository — nothing needs every type as an array, and an unused
     * method kept for symmetry is still an unused method.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC');
    }

    public function countReferencingActivities(ActivityType $activityType): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.activityType = :activityType')
            ->setParameter('activityType', $activityType)
            ->getSingleScalarResult();
    }
}
