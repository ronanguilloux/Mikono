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

    /**
     * Activities tied to this stay at projects of a branch other than the one
     * the stay carries *now* — read before flushing an edit, so a stay can't
     * be moved to another branch away from the projects worked in it
     * (ADR 0027).
     */
    public function countActivitiesAtOtherBranch(Stay $stay): int
    {
        if (null === $stay->getId() || null === $stay->getBranch()) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a JOIN a.program prg JOIN prg.project p WHERE a.stay = :stay AND p.branch <> :branch')
            ->setParameter('stay', $stay)
            ->setParameter('branch', $stay->getBranch())
            ->getSingleScalarResult();
    }

    /** @return list<Stay> */
    public function findStartingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Stay> $stays */
        $stays = $this->createQueryBuilder('s')
            ->addSelect('v', 'b')
            ->join('s.volunteer', 'v')
            ->join('s.branch', 'b')
            ->where('s.startDate BETWEEN :from AND :to')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $stays;
    }

    /**
     * The years from the earliest stay's start to the latest stay's end, as
     * a pair, or null with no stays. Bounds /reports' year picker.
     *
     * @return array{int, int}|null
     */
    public function findYearSpan(): ?array
    {
        /** @var array{first: ?string, last: ?string} $row */
        $row = $this->createQueryBuilder('s')
            ->select('MIN(s.startDate) AS first', 'MAX(s.endDate) AS last')
            ->getQuery()
            ->getSingleResult();

        return null === $row['first'] || null === $row['last']
            ? null
            : [(int) substr($row['first'], 0, 4), (int) substr($row['last'], 0, 4)];
    }
}
