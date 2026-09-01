<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Volunteer>
 */
class VolunteerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Volunteer::class);
    }

    /**
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');
    }

    /** @return Volunteer[] */
    public function findAllOrderedByName(): array
    {
        return $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(Volunteer $volunteer): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.volunteer = :volunteer')
            ->setParameter('volunteer', $volunteer)
            ->getSingleScalarResult();
    }
}
