<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * The paginated index builds on this; findAllOrderedByName() is the same
     * query without a LIMIT, for the callers that genuinely need every row.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');
    }

    /** @return Project[] */
    public function findAllOrderedByName(): array
    {
        return $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    /**
     * Every still-active project with the date it was last worked on, or null
     * if nothing has ever been logged against it. Unlike volunteers, a project
     * with no activity at all is kept — that's the one most in need of
     * attention, not the one to hide.
     *
     * @return list<array{project: Project, lastActivity: \DateTimeImmutable|string|null}>
     */
    public function findActiveWithLastActivityDate(): array
    {
        /** @var list<array{project: Project, lastActivity: \DateTimeImmutable|string|null}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p AS project', 'MAX(a.date) AS lastActivity')
            ->leftJoin(Activity::class, 'a', Join::WITH, 'a.project = p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countReferencingActivities(Project $project): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.project = :project')
            ->setParameter('project', $project)
            ->getSingleScalarResult();
    }

    /**
     * The same count as above for a whole page of projects in one query. The
     * index greys out Delete on the rows the delete-guard would block, and
     * asking per row would be twenty-five COUNT queries a page.
     *
     * Projects with no activities are absent from the result rather than
     * present with a zero, so read it with a `?? 0` default.
     *
     * @param list<Project> $projects
     *
     * @return array<int, int> project id => activities referencing it
     */
    public function countReferencingActivitiesFor(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<array{projectId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(a.project) AS projectId, COUNT(a.id) AS total
                 FROM ' . Activity::class . ' a
                 WHERE a.project IN (:projects)
                 GROUP BY a.project',
            )
            ->setParameter('projects', $projects)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['projectId']] = (int) $row['total'];
        }

        return $counts;
    }
}
