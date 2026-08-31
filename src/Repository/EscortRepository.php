<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Escort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Escort>
 */
class EscortRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Escort::class);
    }

    /** @return Escort[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(Escort $escort): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.accompaniedBy = :escort')
            ->setParameter('escort', $escort)
            ->getSingleScalarResult();
    }
}
