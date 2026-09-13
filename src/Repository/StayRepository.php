<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Stay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stay>
 */
class StayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stay::class);
    }

    public function countReferencingActivities(Stay $stay): int
    {
        if (null === $stay->getId()) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.stay = :stay')
            ->setParameter('stay', $stay)
            ->getSingleScalarResult();
    }

    /**
     * Activities tied to this stay whose date falls outside the dates the stay
     * carries *now* — read before flushing an edit, so a stay can't be shrunk
     * away from the activities logged in it.
     */
    public function countActivitiesOutside(Stay $stay): int
    {
        if (null === $stay->getId() || null === $stay->getStartDate() || null === $stay->getEndDate()) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.stay = :stay AND (a.date < :start OR a.date > :end)')
            ->setParameter('stay', $stay)
            ->setParameter('start', $stay->getStartDate(), Types::DATE_IMMUTABLE)
            ->setParameter('end', $stay->getEndDate(), Types::DATE_IMMUTABLE)
            ->getSingleScalarResult();
    }
}
