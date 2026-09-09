<?php

declare(strict_types=1);

namespace App\Tests\Integration\Usage;

use App\Entity\UsageEvent;
use App\Enum\UsageEventName;
use App\Repository\UsageEventRepository;
use App\Usage\UsageDateRange;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The in-page half of /usage, which has to answer for the same window as the
 * access-log half or the two tables on that screen describe different periods.
 *
 * No factory for UsageEvent: its constructor takes the name and the instant,
 * which is all there is to set.
 */
#[ResetDatabase]
final class UsageEventRepositoryTest extends KernelTestCase
{
    #[Test]
    public function summarizeCountsEveryEventWithoutARange(): void
    {
        $this->record('2026-08-29 10:00', '2026-08-30 10:00', '2026-09-05 10:00');

        $summary = $this->repository()->summarize();

        self::assertCount(1, $summary);
        self::assertSame(3, $summary[0]['count']);
        self::assertSame('2026-09-05', $summary[0]['lastSeen']->format('Y-m-d'));
    }

    #[Test]
    public function summarizeExcludesEventsOutsideTheRange(): void
    {
        $this->record('2026-08-29 10:00', '2026-08-30 10:00', '2026-09-05 10:00');

        $summary = $this->repository()->summarize(
            new UsageDateRange(new \DateTimeImmutable('2026-08-30'), new \DateTimeImmutable('2026-08-31')),
        );

        self::assertCount(1, $summary);
        self::assertSame(1, $summary[0]['count']);
        self::assertSame('2026-08-30', $summary[0]['lastSeen']->format('Y-m-d'));
    }

    /**
     * The upper bound is the whole last day, not midnight on it — the same
     * inclusivity the access-log side gives, or an event logged this afternoon
     * would vanish from a range ending today.
     */
    #[Test]
    public function theLastDayOfARangeIsIncludedInFull(): void
    {
        $this->record('2026-08-31 23:30');

        $summary = $this->repository()->summarize(
            new UsageDateRange(new \DateTimeImmutable('2026-08-31'), new \DateTimeImmutable('2026-08-31')),
        );

        self::assertSame(1, $summary[0]['count'] ?? 0);
    }

    #[Test]
    public function anEmptyWindowSummarizesToNothing(): void
    {
        $this->record('2026-08-29 10:00');

        self::assertSame([], $this->repository()->summarize(
            new UsageDateRange(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-30')),
        ));
    }

    private function record(string ...$instants): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        foreach ($instants as $instant) {
            $entityManager->persist(new UsageEvent(
                UsageEventName::RosterCopied,
                new \DateTimeImmutable($instant),
            ));
        }

        $entityManager->flush();
    }

    private function repository(): UsageEventRepository
    {
        self::bootKernel();

        $repository = self::getContainer()->get(UsageEventRepository::class);
        self::assertInstanceOf(UsageEventRepository::class, $repository);

        return $repository;
    }
}
