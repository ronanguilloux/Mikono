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
}
