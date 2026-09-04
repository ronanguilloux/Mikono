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

    /**
     * The same count as above for a whole page of volunteers in one query. The
     * index greys out Delete on the rows the delete-guard would block, and
     * asking per row would be twenty-five COUNT queries a page.
     *
     * Volunteers with no activities are absent from the result rather than
     * present with a zero, so read it with a `?? 0` default.
     *
     * @param list<Volunteer> $volunteers
     *
     * @return array<int, int> volunteer id => activities referencing them
     */
    public function countReferencingActivitiesFor(array $volunteers): array
    {
        if ([] === $volunteers) {
            return [];
        }

        /** @var list<array{volunteerId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(a.volunteer) AS volunteerId, COUNT(a.id) AS total
                 FROM ' . Activity::class . ' a
                 WHERE a.volunteer IN (:volunteers)
                 GROUP BY a.volunteer',
            )
            ->setParameter('volunteers', $volunteers)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['volunteerId']] = (int) $row['total'];
        }

        return $counts;
    }
}
