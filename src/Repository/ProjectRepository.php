<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** @return Project[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countReferencingActivities(Project $project): int
    {
        // TODO(phase 9): drop this guard once App\Entity\Activity exists —
        // until then nothing can reference a Project, so 0 is correct.
        if (!class_exists(\App\Entity\Activity::class)) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM App\Entity\Activity a WHERE a.project = :project')
            ->setParameter('project', $project)
            ->getSingleScalarResult();
    }
}
