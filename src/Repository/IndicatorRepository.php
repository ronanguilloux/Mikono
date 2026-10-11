<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Indicator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Indicator>
 */
class IndicatorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Indicator::class);
    }

    /**
     * The one ordered-by-name query: the paginated index and the achievement
     * form's picker build on it.
     */
    public function createOrderedByNameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.name', 'ASC');
    }

    public function countReferencingAchievements(Indicator $indicator): int
    {
        return $this->countReferencingAchievementsFor([$indicator])[(int) $indicator->getId()] ?? 0;
    }

    /**
     * The same count for a whole page of indicators, in one query. Indicators
     * no achievement counts are absent, so read it with a `?? 0` default.
     *
     * @param list<Indicator> $indicators
     *
     * @return array<int, int> indicator id => achievements counting it
     */
    public function countReferencingAchievementsFor(array $indicators): array
    {
        if ([] === $indicators) {
            return [];
        }

        /** @var list<array{indicatorId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(ach.indicator) AS indicatorId, COUNT(ach.id) AS total
                 FROM ' . Achievement::class . ' ach
                 WHERE ach.indicator IN (:indicators)
                 GROUP BY ach.indicator',
            )
            ->setParameter('indicators', $indicators)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['indicatorId']] = (int) $row['total'];
        }

        return $counts;
    }
}
