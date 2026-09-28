<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BeneficiaryGroup;
use App\Entity\Program;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BeneficiaryGroup>
 */
class BeneficiaryGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BeneficiaryGroup::class);
    }

    /**
     * The one ordered-by-name query: the paginated index and the program
     * form's picker build on it.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('bg')
            ->orderBy('bg.name', 'ASC');
    }

    public function countReferencingPrograms(BeneficiaryGroup $group): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(prg.id) FROM ' . Program::class . ' prg WHERE :group MEMBER OF prg.beneficiaryGroups')
            ->setParameter('group', $group)
            ->getSingleScalarResult();
    }
}
