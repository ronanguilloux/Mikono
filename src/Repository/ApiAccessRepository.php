<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiAccess;
use App\Usage\UsageDateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiAccess>
 */
class ApiAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiAccess::class);
    }

    public function pruneOlderThan(\DateTimeImmutable $cutoff): void
    {
        $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    /**
     * Who read what in the window, most recent first.
     *
     * @return list<ApiAccess>
     */
    public function findInRange(?UsageDateRange $range = null): array
    {
        $queryBuilder = $this->createQueryBuilder('a')->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC');

        if (null !== $range?->from()) {
            $queryBuilder->andWhere('a.occurredAt >= :from')->setParameter('from', $range->from());
        }

        if (null !== $range?->untilExclusive()) {
            $queryBuilder->andWhere('a.occurredAt < :until')->setParameter('until', $range->untilExclusive());
        }

        /** @var list<ApiAccess> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return $rows;
    }
}
