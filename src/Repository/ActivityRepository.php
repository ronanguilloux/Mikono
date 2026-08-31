<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    /** @return Activity[] */
    public function findAllOrderedByDateDesc(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('v', 'p', 't')
            ->join('a.volunteer', 'v')
            ->join('a.project', 'p')
            ->join('a.activityType', 't')
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * One day's activities, oldest row first — insertion order is the closest
     * thing to the order the VM actually worked through the day, and it's what
     * decides the project-group order on the roster.
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
            ->leftJoin('a.accompaniedBy', 'e')
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
