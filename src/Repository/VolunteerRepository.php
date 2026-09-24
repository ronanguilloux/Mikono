<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Stay;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
     * Active volunteers — those with a stay covering today, read through the
     * HIDDEN `isCurrent` count (ADR 0026) — first, then by name. Volunteers leave after a few
     * weeks, which is why the activity forms already filter their picker to
     * active ones — by surname alone the index drops someone who finished
     * their stint between two people working this week. Nobody is hidden, and
     * clicking any column header still re-orders the whole list: ListPaginator
     * keeps this ORDER BY only as a tie-break (ADR 0011).
     *
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     * The activity forms' volunteer pickers deliberately do *not* reuse it —
     * they order by name alone, without the active-first tie-break above, so that
     * an activity's own deactivated volunteer stays in alphabetical place
     * rather than sinking to the bottom of the dropdown.
     *
     * $search narrows it to volunteers whose full name or email contains the
     * text, ignoring case. Matching the full name rather than each part lets
     * "aisha nj" find Aisha Njoroge; lastName is nullable (ADR 0014), hence
     * the COALESCE, without which CONCAT is null and the name never matches.
     */
    public function createOrderedByNameQueryBuilder(?string $search = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('v')
            ->addSelect('(SELECT COUNT(cs.id) FROM ' . Stay::class . ' cs WHERE cs.volunteer = v AND cs.startDate <= :today AND cs.endDate >= :today) AS HIDDEN isCurrent')
            ->setParameter('today', new \DateTimeImmutable('today'), Types::DATE_IMMUTABLE)
            ->orderBy('isCurrent', 'DESC')
            ->addOrderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');

        if (null !== $search) {
            // `!` escapes LIKE's own wildcards, so "100%" is looked up literally.
            $queryBuilder
                ->andWhere("LOWER(CONCAT(v.firstName, ' ', COALESCE(v.lastName, ''))) LIKE :search ESCAPE '!' OR LOWER(v.email) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)) . '%');
        }

        return $queryBuilder;
    }

    /**
     * Volunteers the activity forms offer: a stay that hasn't ended yet,
     * current or upcoming, so tomorrow's roster can name someone arriving
     * tomorrow. Whether a stay covers the activity's own date is checked on
     * save (ActivityController::resolveStays()), not here.
     */
    public function createWithCurrentOrUpcomingStayQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('v')
            ->where('EXISTS (SELECT us.id FROM ' . Stay::class . ' us WHERE us.volunteer = v AND us.endDate >= :today)')
            ->setParameter('today', new \DateTimeImmutable('today'), Types::DATE_IMMUTABLE)
            ->orderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');
    }

    /**
     * Which of a page of volunteers have a stay covering $day, in one query —
     * the index's Status column, without a stays lazy-load per row.
     *
     * @param list<Volunteer> $volunteers
     *
     * @return array<int, true> volunteer id => true
     */
    public function findIdsStayingOn(array $volunteers, \DateTimeImmutable $day): array
    {
        if ([] === $volunteers) {
            return [];
        }

        /** @var list<int|string> $ids */
        $ids = $this->getEntityManager()
            ->createQuery('SELECT IDENTITY(s.volunteer) FROM ' . Stay::class . ' s WHERE s.volunteer IN (:volunteers) AND s.startDate <= :day AND s.endDate >= :day')
            ->setParameter('volunteers', $volunteers)
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->getSingleColumnResult();

        return array_fill_keys(array_map(intval(...), $ids), true);
    }

    /** Volunteers with a stay covering $day. One stay each at most: stays never overlap. */
    public function countStayingOn(\DateTimeImmutable $day): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(s.id) FROM ' . Stay::class . ' s WHERE s.startDate <= :day AND s.endDate >= :day')
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->getSingleScalarResult();
    }

    /** @return Volunteer[] */
    public function findAllOrderedByName(): array
    {
        // Stays fetched with the volunteers: callers like the /activities
        // filter ask isActive() of every row, and there is no LIMIT here for a
        // to-many join to break.
        return $this->createOrderedByNameQueryBuilder()
            ->leftJoin('v.stays', 's')
            ->addSelect('s')
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
