<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UsageEvent;
use App\Usage\UsageDateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsageEvent>
 */
class UsageEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsageEvent::class);
    }

    /**
     * Counts and last-seen per event name, aggregated in SQL rather than by
     * walking rows: unlike the activity log this table grows with every click,
     * so it is the one place in this app where hydrating everything to count
     * it would eventually hurt.
     *
     * Bounded by the same window as the access-log half of /usage — without
     * it the two tables on that screen would describe different periods. It
     * takes the range object rather than two dates so no caller has to
     * remember that the upper bound is exclusive.
     *
     * @return list<array{name: \App\Enum\UsageEventName, count: int, lastSeen: \DateTimeImmutable}>
     */
    public function summarize(?UsageDateRange $range = null): array
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->select('e.name AS name', 'COUNT(e.id) AS count', 'MAX(e.occurredAt) AS lastSeen')
            ->groupBy('e.name')
            ->orderBy('count', 'DESC');

        if (null !== $range?->from()) {
            $queryBuilder->andWhere('e.occurredAt >= :from')->setParameter('from', $range->from());
        }

        if (null !== $range?->untilExclusive()) {
            $queryBuilder->andWhere('e.occurredAt < :until')->setParameter('until', $range->untilExclusive());
        }

        // COUNT() and MAX() come back driver-formatted — an int-ish string and
        // a date string — because Doctrine only hydrates mapped fields.
        /** @var list<array{name: \App\Enum\UsageEventName, count: int|string, lastSeen: string|\DateTimeImmutable}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return array_map(
            static fn(array $row): array => [
                'name' => $row['name'],
                'count' => (int) $row['count'],
                'lastSeen' => $row['lastSeen'] instanceof \DateTimeImmutable
                    ? $row['lastSeen']
                    : new \DateTimeImmutable($row['lastSeen']),
            ],
            $rows,
        );
    }
}
