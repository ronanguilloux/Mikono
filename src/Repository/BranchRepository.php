<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Stay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Branch>
 */
class BranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.name', 'ASC');
    }

    /** The delete-guard: a branch that stays happened at can't be deleted (ADR 0025). */
    public function countReferencingStays(Branch $branch): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(s.id) FROM ' . Stay::class . ' s WHERE s.branch = :branch')
            ->setParameter('branch', $branch)
            ->getSingleScalarResult();
    }

    /**
     * The same count for a whole page of branches in one query. Branches with
     * no stays are absent, so read it with a `?? 0` default.
     *
     * @param list<Branch> $branches
     *
     * @return array<int, int> branch id => stays referencing it
     */
    public function countReferencingStaysFor(array $branches): array
    {
        if ([] === $branches) {
            return [];
        }

        /** @var list<array{branchId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(s.branch) AS branchId, COUNT(s.id) AS total
                 FROM ' . Stay::class . ' s
                 WHERE s.branch IN (:branches)
                 GROUP BY s.branch',
            )
            ->setParameter('branches', $branches)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['branchId']] = (int) $row['total'];
        }

        return $counts;
    }
}
