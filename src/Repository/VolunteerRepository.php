<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** @return Volunteer[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(Volunteer $volunteer): int
    {
        // TODO(phase 9): drop this guard once App\Entity\Activity exists —
        // until then nothing can reference a Volunteer, so 0 is correct.
        if (!class_exists(\App\Entity\Activity::class)) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM App\Entity\Activity a WHERE a.volunteer = :volunteer')
            ->setParameter('volunteer', $volunteer)
            ->getSingleScalarResult();
    }
}
