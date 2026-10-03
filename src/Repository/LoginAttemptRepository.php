<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Usage\UsageDateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Attempts per (identifier, IP), most recent first, with the app user the
     * identifier belongs to — null for an address that matches no account,
     * which is an attempt that could never have succeeded. Bounded by the
     * same window as the rest of /usage, like UsageEventRepository::summarize().
     *
     * Matched case-insensitively: the address is stored as typed, and
     * "VM@example.org" is still somebody's account to the person reading.
     *
     * ponytail: the whole result is returned and paginated in memory —
     * bounded by the 90-day retention, not by a cap. Doctrine's paginator
     * can't reliably count a GROUP BY query; move the count into SQL if a
     * credential spray ever makes this heavy.
     *
     * @return list<array{identifier: ?string, ip: ?string, succeeded: int, failed: int, lastAttempt: \DateTimeImmutable, userId: ?int, userName: ?string}>
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
                'u.id AS userId',
                'u.fullName AS userName',
            )
            ->leftJoin(User::class, 'u', 'WITH', 'LOWER(u.email) = LOWER(a.identifier)')
            ->groupBy('a.identifier', 'a.ip', 'u.id', 'u.fullName')
            ->orderBy('lastAttempt', 'DESC');

        if (null !== $range?->from()) {
            $queryBuilder->andWhere('a.occurredAt >= :from')->setParameter('from', $range->from());
        }

        if (null !== $range?->untilExclusive()) {
            $queryBuilder->andWhere('a.occurredAt < :until')->setParameter('until', $range->untilExclusive());
        }

        // Aggregates come back driver-formatted, as in UsageEventRepository.
        /** @var list<array{identifier: ?string, ip: ?string, succeeded: int|string, failed: int|string, lastAttempt: string|\DateTimeImmutable, userId: int|string|null, userName: ?string}> $rows */
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
                'userId' => null === $row['userId'] ? null : (int) $row['userId'],
                'userName' => $row['userName'],
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
