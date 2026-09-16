<?php

declare(strict_types=1);

namespace App\Tests\Integration\Usage;

use App\Entity\LoginAttempt;
use App\Repository\LoginAttemptRepository;
use App\Usage\UsageDateRange;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The Sign-ins half of /usage (ADR 0028). No factory, as for UsageEvent: the
 * constructor takes everything there is to set.
 */
#[ResetDatabase]
final class LoginAttemptRepositoryTest extends KernelTestCase
{
    #[Test]
    public function summarizeGroupsByIdentifierAndIpWithFailuresFirst(): void
    {
        $this->record('vm@example.org', true, '10.0.0.1', '2026-09-01 08:00');
        $this->record('vm@example.org', true, '10.0.0.1', '2026-09-02 08:00');
        $this->record('vm@example.org', false, '10.0.0.9', '2026-09-01 09:00');
        $this->record('vm@example.org', false, '10.0.0.9', '2026-09-01 09:01');
        $this->record(null, false, '10.0.0.9', '2026-09-01 09:02');

        $summary = $this->repository()->summarize();

        self::assertCount(3, $summary);
        self::assertSame(['vm@example.org', '10.0.0.9', 0, 2], [$summary[0]['identifier'], $summary[0]['ip'], $summary[0]['succeeded'], $summary[0]['failed']]);
        self::assertSame('2026-09-01 09:01', $summary[0]['lastAttempt']->format('Y-m-d H:i'));
        self::assertSame([null, 1], [$summary[1]['identifier'], $summary[1]['failed']]);
        self::assertSame([2, 0], [$summary[2]['succeeded'], $summary[2]['failed']]);
    }

    #[Test]
    public function summarizeExcludesAttemptsOutsideTheRange(): void
    {
        $this->record('vm@example.org', true, null, '2026-08-29 10:00');
        $this->record('vm@example.org', true, null, '2026-08-30 10:00');
        $this->record('vm@example.org', true, null, '2026-08-31 10:00');

        $summary = $this->repository()->summarize(
            new UsageDateRange(new \DateTimeImmutable('2026-08-30'), new \DateTimeImmutable('2026-08-30')),
        );

        self::assertCount(1, $summary);
        self::assertSame(1, $summary[0]['succeeded']);
    }

    #[Test]
    public function pruneDeletesOnlyAttemptsBeforeTheCutoff(): void
    {
        $this->record('old@example.org', false, null, '2026-06-01 10:00');
        $this->record('new@example.org', false, null, '2026-09-01 10:00');

        self::assertSame(1, $this->repository()->pruneOlderThan(new \DateTimeImmutable('2026-08-01')));

        $summary = $this->repository()->summarize();
        self::assertCount(1, $summary);
        self::assertSame('new@example.org', $summary[0]['identifier']);
    }

    private function record(?string $identifier, bool $succeeded, ?string $ip, string $at): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new LoginAttempt($identifier, $succeeded, $ip, new \DateTimeImmutable($at)));
        $entityManager->flush();
    }

    private function repository(): LoginAttemptRepository
    {
        return self::getContainer()->get(LoginAttemptRepository::class);
    }
}
