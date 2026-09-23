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
     * Every escort, ordered by name: the paginated index and its export build
     * on it. The activity pickers want createActiveOrderedByNameQueryBuilder().
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

    /**
     * Active escorts only, for both activity pickers. Deactivating is how a
     * staff member who left is retired, since the delete-guard blocks
     * deleting anyone with logged activities.
     *
     * $alsoInclude is the edit screen's escape hatch: an activity's own
     * escorts stay offered once deactivated, or saving the form unchanged
     * would silently drop them.
     *
     * @param list<Escort> $alsoInclude
     */
    public function createActiveOrderedByNameQueryBuilder(array $alsoInclude = []): QueryBuilder
    {
        $builder = $this->createOrderedByNameQueryBuilder()
            ->where('e.isActive = true');

        if ([] !== $alsoInclude) {
            $builder->orWhere('e IN (:current)')->setParameter('current', $alsoInclude);
        }

        return $builder;
    }

    public function countReferencingActivities(Escort $escort): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE :escort MEMBER OF a.escorts')
            ->setParameter('escort', $escort)
            ->getSingleScalarResult();
    }
}
