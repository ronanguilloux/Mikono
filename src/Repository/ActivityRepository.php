<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
