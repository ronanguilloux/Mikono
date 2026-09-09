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
     * The one ordered-by-name query: the paginated index builds on it, and both
     * activity forms hand it straight to their escort picker.
     *
     * No findAllOrderedByName() twin here, unlike VolunteerRepository and
     * ProjectRepository — nothing needs every escort as an array, and an
     * unused method kept for symmetry is still an unused method.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.name', 'ASC');
    }

    public function countReferencingActivities(Escort $escort): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE :escort MEMBER OF a.escorts')
            ->setParameter('escort', $escort)
            ->getSingleScalarResult();
    }
}
