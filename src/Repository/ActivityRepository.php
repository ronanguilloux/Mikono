<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
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
     * row. All three joins are to-one, so a LIMIT can't multiply rows and the
     * page size means what it says.
     */
    public function createOrderedByDateDescQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->addSelect('v', 'p', 't')
            ->join('a.volunteer', 'v')
            ->join('a.project', 'p')
            ->join('a.activityType', 't')
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.id', 'DESC');
    }

    /** @return Activity[] */
    public function findAllOrderedByDateDesc(): array
    {
        return $this->createOrderedByDateDescQueryBuilder()
            ->getQuery()
            ->getResult();
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
            ->addSelect('v', 'p', 't', 'e')
            ->join('a.volunteer', 'v')
            ->join('a.project', 'p')
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
        return $this->createQueryBuilder('a')
            ->addSelect('v', 'p', 't')
            ->join('a.volunteer', 'v')
            ->join('a.project', 'p')
            ->join('a.activityType', 't')
            ->where('a.volunteer = :volunteer')
            ->setParameter('volunteer', $volunteer)
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
