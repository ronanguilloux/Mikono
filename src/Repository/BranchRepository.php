<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Project;
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

    /**
     * The delete-guard: a branch that stays or projects point to can't be
     * deleted (ADR 0025, ADR 0027).
     */
    public function countReferences(Branch $branch): int
    {
        return $this->countReferencesFor([$branch])[(int) $branch->getId()] ?? 0;
    }

    /**
     * The same count for a whole page of branches, one query per referencing
     * entity. Branches nothing points to are absent, so read it with a `?? 0`
     * default.
     *
     * @param list<Branch> $branches
     *
     * @return array<int, int> branch id => stays and projects referencing it
     */
    public function countReferencesFor(array $branches): array
    {
        if ([] === $branches) {
            return [];
        }

        $counts = [];
        foreach ([Stay::class, Project::class] as $class) {
            /** @var list<array{branchId: int|string, total: int|string}> $rows */
            $rows = $this->getEntityManager()
                ->createQuery(
                    'SELECT IDENTITY(r.branch) AS branchId, COUNT(r.id) AS total
                     FROM ' . $class . ' r
                     WHERE r.branch IN (:branches)
                     GROUP BY r.branch',
                )
                ->setParameter('branches', $branches)
                ->getResult();

            foreach ($rows as $row) {
                $id = (int) $row['branchId'];
                $counts[$id] = ($counts[$id] ?? 0) + (int) $row['total'];
            }
        }

        return $counts;
    }
}
