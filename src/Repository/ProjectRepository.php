<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
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
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM '.Activity::class.' a WHERE a.project = :project')
            ->setParameter('project', $project)
            ->getSingleScalarResult();
    }
}
