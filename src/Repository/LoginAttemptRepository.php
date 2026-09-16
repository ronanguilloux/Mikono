<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginAttempt;
use App\Usage\UsageDateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    /**
     * ponytail: a fixed cap, so a spray of distinct identifiers can't grow the
     * /usage page without bound. Paginate if the cap is ever reached.
     */
    public const int SUMMARY_LIMIT = 50;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Attempts per (identifier, IP), failures first — "who is failing to get
     * in, from where" is the question. Bounded by the same window as the rest
     * of /usage, like UsageEventRepository::summarize().
     *
     * @return list<array{identifier: ?string, ip: ?string, succeeded: int, failed: int, lastAttempt: \DateTimeImmutable}>
     */
    public function summarize(?UsageDateRange $range = null): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select(
                'a.identifier AS identifier',
                'a.ip AS ip',
                'SUM(CASE WHEN a.succeeded = true THEN 1 ELSE 0 END) AS succeeded',
                'SUM(CASE WHEN a.succeeded = true THEN 0 ELSE 1 END) AS failed',
                'MAX(a.occurredAt) AS lastAttempt',
            )
            ->groupBy('a.identifier', 'a.ip')
            ->orderBy('failed', 'DESC')
            ->addOrderBy('lastAttempt', 'DESC')
            ->setMaxResults(self::SUMMARY_LIMIT);

        if (null !== $range?->from()) {
            $queryBuilder->andWhere('a.occurredAt >= :from')->setParameter('from', $range->from());
        }

        if (null !== $range?->untilExclusive()) {
            $queryBuilder->andWhere('a.occurredAt < :until')->setParameter('until', $range->untilExclusive());
        }

        // Aggregates come back driver-formatted, as in UsageEventRepository.
        /** @var list<array{identifier: ?string, ip: ?string, succeeded: int|string, failed: int|string, lastAttempt: string|\DateTimeImmutable}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return array_map(
            static fn(array $row): array => [
                'identifier' => $row['identifier'],
                'ip' => $row['ip'],
                'succeeded' => (int) $row['succeeded'],
                'failed' => (int) $row['failed'],
                'lastAttempt' => $row['lastAttempt'] instanceof \DateTimeImmutable
                    ? $row['lastAttempt']
                    : new \DateTimeImmutable($row['lastAttempt']),
            ],
            $rows,
        );
    }

    public function pruneOlderThan(\DateTimeImmutable $cutoff): int
    {
        /** @var int $deleted */
        $deleted = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return $deleted;
    }
}
