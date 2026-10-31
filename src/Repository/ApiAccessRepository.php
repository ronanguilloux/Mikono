<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiAccess;
use App\Usage\UsageDateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;

/**
 * @extends ServiceEntityRepository<ApiAccess>
 */
class ApiAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiAccess::class);
    }

    public function pruneOlderThan(\DateTimeImmutable $cutoff): void
    {
        $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    /** When this account last read anything through the API, if ever. */
    public function lastReadBy(string $identifier): ?\DateTimeImmutable
    {
        $access = $this->findOneBy(['identifier' => $identifier], ['occurredAt' => 'DESC']);

        return $access?->getOccurredAt();
    }

    /**
     * This account's reads through MCP, newest first, matched like sign-ins:
     * on the current email, ignoring case.
     *
     * @return list<ApiAccess>
     */
    public function findMcpReadsBy(string $identifier, int $limit): array
    {
        /** @var list<ApiAccess> $rows */
        $rows = $this->mcpReadsBy($identifier)
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countMcpReadsBy(string $identifier): int
    {
        return (int) $this->mcpReadsBy($identifier)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
    }

    private function mcpReadsBy(string $identifier): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.identifier) = LOWER(:identifier)')
            ->andWhere('a.viaMcp = true')
            ->setParameter('identifier', $identifier);
    }

    /** A live refresh token is what keeps a connection signed in past the hour. */
    public function isConnected(string $identifier): bool
    {
        return 0 < $this->getEntityManager()
            ->createQuery(\sprintf('SELECT COUNT(rt) FROM %s rt JOIN rt.accessToken at WHERE at.userIdentifier = :identifier AND rt.revoked = false AND rt.expiry > :now', RefreshToken::class))
            ->setParameter('identifier', $identifier)
            ->setParameter('now', new \DateTimeImmutable())
            ->getSingleScalarResult();
    }

    /**
     * Who read what in the window, most recent first.
     *
     * @return list<ApiAccess>
     */
    public function findInRange(?UsageDateRange $range = null): array
    {
        $queryBuilder = $this->createQueryBuilder('a')->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC');

        if (null !== $range?->from()) {
            $queryBuilder->andWhere('a.occurredAt >= :from')->setParameter('from', $range->from());
        }

        if (null !== $range?->untilExclusive()) {
            $queryBuilder->andWhere('a.occurredAt < :until')->setParameter('until', $range->untilExclusive());
        }

        /** @var list<ApiAccess> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return $rows;
    }
}
