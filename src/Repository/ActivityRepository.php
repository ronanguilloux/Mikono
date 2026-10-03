<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * The paginated index builds on this; findAllOrderedByDateDesc() is the
     * same query without a LIMIT, for the callers that genuinely need every
     * row. All four joins are to-one, so a LIMIT can't multiply rows and the
     * page size means what it says.
     *
     * A volunteer narrows the result to that person's activities — the index's
     * `?volunteer=<id>` filter and the volunteer screen's activity history are
     * the same query. A program narrows it the same way (`?program=<id>`);
     * both reach DQL as bound parameters, never interpolated. A branch
     * (`?branch=<id>`) filters on the stay's branch, where ADR 0026 anchors an
     * activity's branch; its join is to-one too and only added when filtering.
     */
    public function createOrderedByDateDescQueryBuilder(?Volunteer $volunteer = null, ?Program $program = null, ?Branch $branch = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->addSelect('v', 'prg', 'p', 't')
            ->join('a.volunteer', 'v')
            ->join('a.program', 'prg')
            ->join('prg.project', 'p')
            ->join('a.activityType', 't')
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (null !== $volunteer) {
            $queryBuilder
                ->andWhere('a.volunteer = :volunteer')
                ->setParameter('volunteer', $volunteer);
        }

        if (null !== $program) {
            $queryBuilder
                ->andWhere('a.program = :program')
                ->setParameter('program', $program);
        }

        if (null !== $branch) {
            $queryBuilder
                ->join('a.stay', 's')
                ->andWhere('s.branch = :branch')
                ->setParameter('branch', $branch);
        }

        return $queryBuilder;
    }

    /** @return Activity[] */
    public function findAllOrderedByDateDesc(): array
    {
        return $this->createOrderedByDateDescQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    /**
     * Each activity's date (as stored, `Y-m-d`) and volunteer, nothing
     * hydrated, for the monthly totals. A branch filters on the stay's
     * branch, where ADR 0026 anchors an activity's branch.
     *
     * @return list<array{date: string, volunteerId: int|string}>
     */
    public function findVolunteerDates(?Branch $branch = null): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('a.date AS date', 'IDENTITY(a.volunteer) AS volunteerId');

        if (null !== $branch) {
            $queryBuilder
                ->join('a.stay', 's')
                ->andWhere('s.branch = :branch')
                ->setParameter('branch', $branch);
        }

        /** @var list<array{date: string, volunteerId: int|string}> $rows */
        $rows = $queryBuilder->getQuery()->getScalarResult();

        return $rows;
    }

    /**
     * Activities dated between two days inclusive, each volunteer's sources
     * fetched in the same query, for the per-source report (ADR 0041). The
     * sources join is to-many, which is fine here: no LIMIT.
     *
     * @return Activity[]
     */
    public function findDatedBetweenWithSources(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var Activity[] $activities */
        $activities = $this->createOrderedByDateDescQueryBuilder()
            ->leftJoin('v.sources', 'src')
            ->addSelect('src')
            ->andWhere('a.date BETWEEN :from AND :to')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $activities;
    }

    /**
     * Every activity with its escorts fetched in the same query, for the
     * per-escort report. The escorts join is to-many (ADR 0013), which is
     * fine here: no LIMIT, the whole table is read anyway.
     *
     * @return Activity[]
     */
    public function findAllWithEscorts(): array
    {
        /** @var Activity[] $activities */
        $activities = $this->createOrderedByDateDescQueryBuilder()
            ->leftJoin('a.escorts', 'e')
            ->addSelect('e')
            ->getQuery()
            ->getResult();

        return $activities;
    }

    /**
     * One day's activities, oldest row first — insertion order is the closest
     * thing to the order the VM actually worked through the day, and it's what
     * decides the project-group order on the roster.
     *
     * The escorts join is to-many (ADR 0013), so this query can't grow a LIMIT
     * without multiplying rows. It doesn't need one — it loads a single day —
     * but the paginated index deliberately doesn't join escorts at all.
     *
     * @return Activity[]
     */
    public function findByDate(\DateTimeImmutable $date): array
    {
        /** @var Activity[] $activities */
        $activities = $this->createQueryBuilder('a')
            ->addSelect('v', 'prg', 'p', 't', 'e')
            ->join('a.volunteer', 'v')
            ->join('a.program', 'prg')
            ->join('prg.project', 'p')
            ->join('a.activityType', 't')
            ->leftJoin('a.escorts', 'e')
            ->where('a.date = :date')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $activities;
    }

    /** @return Activity[] */
    public function findByVolunteerOrderedByDateDesc(Volunteer $volunteer): array
    {
        return $this->createOrderedByDateDescQueryBuilder($volunteer)
            ->getQuery()
            ->getResult();
    }

    /**
     * What these volunteers have done so far, by activity type and program:
     * the experience /matches counts (ADR 0042). Planned activities, dated
     * after today, aren't experience yet.
     *
     * @param list<int> $volunteerIds
     *
     * @return list<array{volunteerId: int, typeId: int, programId: int, total: int, last: \DateTimeImmutable}>
     */
    public function findExperienceOf(array $volunteerIds, \DateTimeImmutable $today): array
    {
        if ([] === $volunteerIds) {
            return [];
        }

        /** @var list<array{volunteerId: int|string, typeId: int|string, programId: int|string, total: int|string, last: string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.volunteer) AS volunteerId', 'IDENTITY(a.activityType) AS typeId', 'IDENTITY(a.program) AS programId', 'COUNT(a.id) AS total', 'MAX(a.date) AS last')
            ->where('a.volunteer IN (:volunteers)')
            ->andWhere('a.date <= :today')
            ->setParameter('volunteers', $volunteerIds)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->groupBy('a.volunteer', 'a.activityType', 'a.program')
            ->getQuery()
            ->getResult();

        // MAX() bypasses Doctrine's type conversion: the date comes back raw.
        return array_map(static fn(array $row): array => [
            'volunteerId' => (int) $row['volunteerId'],
            'typeId' => (int) $row['typeId'],
            'programId' => (int) $row['programId'],
            'total' => (int) $row['total'],
            'last' => new \DateTimeImmutable($row['last']),
        ], $rows);
    }

    /**
     * Distinct volunteers with a past activity in each given program, and in
     * each given project — a project's count is distinct across its programs,
     * not the sum of theirs. Keys are ids; an id with no match is absent.
     *
     * @param list<int> $programIds
     * @param list<int> $projectIds
     *
     * @return array{programs: array<int, int>, projects: array<int, int>}
     */
    public function countVolunteersEngaged(array $programIds, array $projectIds, \DateTimeImmutable $today): array
    {
        return [
            'programs' => $this->countDistinctVolunteersBy('a.program', $programIds, $today),
            'projects' => $this->countDistinctVolunteersBy('prg.project', $projectIds, $today),
        ];
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function countDistinctVolunteersBy(string $association, array $ids, \DateTimeImmutable $today): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<array{id: int|string, n: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select("IDENTITY({$association}) AS id", 'COUNT(DISTINCT a.volunteer) AS n')
            ->join('a.program', 'prg')
            ->where("IDENTITY({$association}) IN (:ids)")
            ->andWhere('a.date <= :today')
            ->groupBy('id')
            ->setParameter('ids', $ids)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['n'];
        }

        return $counts;
    }
}
