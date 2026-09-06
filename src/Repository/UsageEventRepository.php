<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UsageEvent;
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
     * @return list<array{name: \App\Enum\UsageEventName, count: int, lastSeen: \DateTimeImmutable}>
     */
    public function summarize(): array
    {
        // COUNT() and MAX() come back driver-formatted — an int-ish string and
        // a date string — because Doctrine only hydrates mapped fields.
        /** @var list<array{name: \App\Enum\UsageEventName, count: int|string, lastSeen: string|\DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.name AS name', 'COUNT(e.id) AS count', 'MAX(e.occurredAt) AS lastSeen')
            ->groupBy('e.name')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

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
