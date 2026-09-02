<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Escort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.name', 'ASC');
    }

    /** @return Escort[] */
    public function findAllOrderedByName(): array
    {
        return $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(Escort $escort): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE :escort MEMBER OF a.escorts')
            ->setParameter('escort', $escort)
            ->getSingleScalarResult();
    }
}
