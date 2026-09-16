<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\Program;
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
     * The one ordered-by-name query: the paginated index and the program form
     * build on it.
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

    /**
     * The activity forms' picker: only types some program offers, since an
     * activity's type must be offered by its program (ADR 0030).
     */
    public function createOfferedOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createOrderedByNameQueryBuilder()
            ->where('EXISTS (SELECT prg.id FROM ' . Program::class . ' prg WHERE t MEMBER OF prg.activityTypes)');
    }

    public function countReferencingActivities(ActivityType $activityType): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.activityType = :activityType')
            ->setParameter('activityType', $activityType)
            ->getSingleScalarResult();
    }

    public function countReferencingPrograms(ActivityType $activityType): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(prg.id) FROM ' . Program::class . ' prg WHERE :activityType MEMBER OF prg.activityTypes')
            ->setParameter('activityType', $activityType)
            ->getSingleScalarResult();
    }
}
