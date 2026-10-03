<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Skill;
use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Enum\VolunteerStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Volunteer>
 */
class VolunteerRepository extends ServiceEntityRepository
{
    /** DQL conditions on a stay, with %1$s standing for its alias. */
    private const string COVERS_TODAY = ' AND %1$s.startDate <= :today AND %1$s.endDate >= :today';

    private const string STARTS_AFTER_TODAY = ' AND %1$s.startDate > :today';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Volunteer::class);
    }

    /**
     * Volunteers by status — present, upcoming, past, then those with no stay,
     * read through the HIDDEN `statusRank` (ADR 0026) — then by name. Volunteers
     * leave after a few weeks, which is why the activity forms already filter
     * their picker by stay dates — by surname alone the index drops someone
     * who finished their stint between two people working this week. Nobody is
     * hidden, and clicking any column header still re-orders the whole list:
     * ListPaginator keeps this ORDER BY only as a tie-break (ADR 0011).
     *
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     * The activity forms' volunteer pickers deliberately do *not* reuse it —
     * they order by name alone, without the status-first tie-break above, so that
     * an activity's own deactivated volunteer stays in alphabetical place
     * rather than sinking to the bottom of the dropdown.
     *
     * $search narrows it to volunteers whose full name or email contains the
     * text, ignoring case. Matching the full name rather than each part lets
     * "aisha nj" find Aisha Njoroge; lastName is nullable (ADR 0014), hence
     * the COALESCE, without which CONCAT is null and the name never matches.
     *
     * $status narrows to volunteers with that status; $branch to those with a
     * stay at that branch. Both set means the stay that gives the status:
     * present at Kibera after a past Mombasa stay is not present at Mombasa,
     * and upcoming at Mombasa means the upcoming stay is there. Past at a
     * branch is any stay there, since every stay of a past volunteer ended.
     */
    public function createOrderedByNameQueryBuilder(?string $search = null, ?Skill $skill = null, ?VolunteerStatus $status = null, ?Branch $branch = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('v')
            ->addSelect(self::statusRank('r') . ' AS HIDDEN statusRank')
            ->setParameter('today', new \DateTimeImmutable('today'), Types::DATE_IMMUTABLE)
            ->orderBy('statusRank', 'ASC')
            ->addOrderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');

        if (null !== $search) {
            // `!` escapes LIKE's own wildcards, so "100%" is looked up literally.
            $queryBuilder
                ->andWhere("LOWER(CONCAT(v.firstName, ' ', COALESCE(v.lastName, ''))) LIKE :search ESCAPE '!' OR LOWER(v.email) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)) . '%');
        }

        if (null !== $skill) {
            $queryBuilder
                ->andWhere(':skill MEMBER OF v.skills')
                ->setParameter('skill', $skill);
        }

        if (null !== $status) {
            // WHERE can't name the HIDDEN select, so the rank is spelled again.
            $queryBuilder
                ->andWhere(self::statusRank('f') . ' = :rank')
                ->setParameter('rank', array_search($status, VolunteerStatus::cases(), true));
        }

        if (null !== $branch) {
            $stay = match ($status) {
                VolunteerStatus::Present => self::COVERS_TODAY,
                VolunteerStatus::Upcoming => self::STARTS_AFTER_TODAY,
                default => '',
            };
            $queryBuilder
                ->andWhere(self::stayExists('bs', $stay . ' AND %1$s.branch = :branch'))
                ->setParameter('branch', $branch);
        }

        return $queryBuilder;
    }

    /**
     * Volunteer::getStatus() in DQL, as the status's position in
     * VolunteerStatus::cases(). $prefix keeps the subquery aliases unique,
     * which DQL requires across the whole query.
     */
    private static function statusRank(string $prefix): string
    {
        return sprintf(
            'CASE WHEN %s THEN 0 WHEN %s THEN 1 WHEN %s THEN 2 ELSE 3 END',
            self::stayExists($prefix . 'p', self::COVERS_TODAY),
            self::stayExists($prefix . 'u', self::STARTS_AFTER_TODAY),
            self::stayExists($prefix . 'a'),
        );
    }

    /** EXISTS over v's stays, $where written against %1$s for the alias. */
    private static function stayExists(string $alias, string $where = ''): string
    {
        return sprintf('EXISTS (SELECT %1$s.id FROM ' . Stay::class . ' %1$s WHERE %1$s.volunteer = v' . $where . ')', $alias);
    }

    /**
     * Volunteers holding at least one of the program's skills, most matched
     * skills first, then by status, then by name — the order is the answer, so
     * /programs/{id}/matches has no sort links. A program with no skills
     * matches nobody. See ADR 0036.
     */
    public function createMatchingProgramQueryBuilder(Program $program): QueryBuilder
    {
        return $this->createOrderedByNameQueryBuilder()
            ->innerJoin('v.skills', 'ms')
            ->andWhere('ms.id IN (SELECT ps.id FROM ' . Program::class . ' mp JOIN mp.skills ps WHERE mp = :program)')
            ->setParameter('program', $program)
            ->addSelect('COUNT(ms.id) AS HIDDEN matched')
            ->groupBy('v.id')
            ->orderBy('matched', 'DESC')
            ->addOrderBy('statusRank', 'ASC')
            ->addOrderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');
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
     * Volunteers the batch form offers: anyone with a stay, stays fetched in
     * the same query so the form can tell the browser which dates each one
     * covers — logging a past session names people who have since left.
     */
    public function createWithAnyStayQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.stays', 's')
            ->addSelect('s')
            ->orderBy('v.lastName', 'ASC')
            ->addOrderBy('v.firstName', 'ASC');
    }

    /**
     * A list's Status cells in one query rather than a stays lazy-load per
     * row: fetch-joining the stays fills the volunteers' own collections, so
     * Volunteer::getStatus() stays the one rule in PHP.
     *
     * @param list<Volunteer> $volunteers
     *
     * @return array<int, VolunteerStatus> volunteer id => status
     */
    public function findStatusesOn(array $volunteers, \DateTimeImmutable $today): array
    {
        if ([] === $volunteers) {
            return [];
        }

        $this->createQueryBuilder('v')
            ->leftJoin('v.stays', 's')
            ->addSelect('s')
            ->where('v IN (:volunteers)')
            ->setParameter('volunteers', $volunteers)
            ->getQuery()
            ->getResult();

        $statuses = [];
        foreach ($volunteers as $volunteer) {
            $statuses[(int) $volunteer->getId()] = $volunteer->getStatus($today);
        }

        return $statuses;
    }

    /** @return Volunteer[] */
    public function findAllOrderedByName(): array
    {
        // Stays fetched with the volunteers: ReportMetricsCalculator asks
        // getStatus() of every row, and there is no LIMIT here for a to-many
        // join to break.
        return $this->createOrderedByNameQueryBuilder()
            ->leftJoin('v.stays', 's')
            ->addSelect('s')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every volunteer with a date of birth, active or not, for the home
     * screen's birthday reminders. Month/day matching is left to PHP: SQLite
     * has no portable way to do it, and a few hundred rows cost nothing.
     *
     * @return Volunteer[]
     */
    public function findWithDateOfBirth(): array
    {
        /** @var Volunteer[] $volunteers */
        $volunteers = $this->createQueryBuilder('v')
            ->where('v.dateOfBirth IS NOT NULL')
            ->getQuery()
            ->getResult();

        return $volunteers;
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
