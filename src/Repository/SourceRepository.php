<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Source;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * The one ordered-by-name query: the paginated index, the volunteer
     * form's picker and the volunteer list's filter build on it.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC');
    }

    /**
     * @return list<Source>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<Source> $sources */
        $sources = $this->createOrderedByNameQueryBuilder()
            ->getQuery()
            ->getResult();

        return $sources;
    }

    public function countReferencingVolunteers(Source $source): int
    {
        return $this->countReferencingVolunteersFor([$source])[(int) $source->getId()] ?? 0;
    }

    /**
     * The same count for a whole page of sources, in one query. Sources no
     * volunteer holds are absent, so read it with a `?? 0` default.
     *
     * @param list<Source> $sources
     *
     * @return array<int, int> source id => volunteers holding it
     */
    public function countReferencingVolunteersFor(array $sources): array
    {
        if ([] === $sources) {
            return [];
        }

        /** @var list<array{sourceId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT s.id AS sourceId, COUNT(v.id) AS total
                 FROM ' . Volunteer::class . ' v
                 JOIN v.sources s
                 WHERE s IN (:sources)
                 GROUP BY s.id',
            )
            ->setParameter('sources', $sources)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['sourceId']] = (int) $row['total'];
        }

        return $counts;
    }
}
