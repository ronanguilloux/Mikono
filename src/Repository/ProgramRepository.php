<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Program;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Program>
 */
class ProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Program::class);
    }

    /**
     * Project and branch are fetch-joined: the index shows and sorts by both.
     */
    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('prg')
            ->join('prg.project', 'p')
            ->join('p.branch', 'b')
            ->addSelect('p', 'b')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('prg.name', 'ASC');
    }

    /**
     * Part of the project delete-guard: a project's programs would otherwise
     * be orphaned (ADR 0030).
     */
    public function countForProject(Project $project): int
    {
        return $this->count(['project' => $project]);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, int> project id => its programs; absent means none
     */
    public function countForProjects(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<array{projectId: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('prg')
            ->select('IDENTITY(prg.project) AS projectId', 'COUNT(prg.id) AS total')
            ->where('prg.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->groupBy('prg.project')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['projectId']] = (int) $row['total'];
        }

        return $counts;
    }
}
