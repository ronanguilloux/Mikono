<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Program;
use App\Entity\Skill;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Skill>
 */
class SkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skill::class);
    }

    /**
     * The one ordered-by-name query: the paginated index, both forms' pickers
     * and the volunteer list's filter build on it.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC');
    }

    /**
     * @return list<Skill>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<Skill> $skills */
        $skills = $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();

        return $skills;
    }

    public function countReferencingVolunteers(Skill $skill): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(v.id) FROM ' . Volunteer::class . ' v WHERE :skill MEMBER OF v.skills')
            ->setParameter('skill', $skill)
            ->getSingleScalarResult();
    }

    public function countReferencingPrograms(Skill $skill): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(prg.id) FROM ' . Program::class . ' prg WHERE :skill MEMBER OF prg.skills')
            ->setParameter('skill', $skill)
            ->getSingleScalarResult();
    }
}
