<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Branch;
use App\Factory\BranchFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\OccupancyLookAhead;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

/**
 * Today is Sunday 11 October 2026: the weeks start on Monday 5 October.
 * Branches are the five seeded by their migration (ADR 0025).
 */
#[ResetDatabase]
final class OccupancyLookAheadTest extends KernelTestCase
{
    /**
     * Two stays that never share a day make a week of 1, not 2: a week is
     * its busiest day, since beds run out on a day.
     */
    #[Test]
    public function aWeekCountsItsBusiestDayAndSaysWhenCapacityIsFirstPassed(): void
    {
        self::bootKernel();
        $mombasa = self::branch('Mombasa', 2);
        self::stay($mombasa, '2026-10-05', '2026-10-06'); // Mon–Tue, week 1
        self::stay($mombasa, '2026-10-08', '2026-10-09'); // Thu–Fri, week 1, no shared day
        self::stay($mombasa, '2026-10-19', '2026-10-29'); // weeks 3 and 4
        self::stay($mombasa, '2026-10-20', '2026-10-27'); // two in week 3: at capacity, not over
        self::stay($mombasa, '2026-10-26', '2026-11-02'); // three on 26–27 Oct: week 4; Monday 2 Nov: week 5

        $row = self::rowFor('Mombasa');

        self::assertSame(2, $row['capacity']);
        self::assertCount(12, $row['weeks']);
        self::assertSame('2026-10-05', $row['weeks'][0]['start']->format('Y-m-d'));
        self::assertSame([1, 0, 2, 3, 1], array_map(static fn(array $w): int => $w['peak'], array_slice($row['weeks'], 0, 5)));
        self::assertSame('2026-10-26', $row['firstOver']?->format('Y-m-d'));
    }

    #[Test]
    public function aBranchWithNeitherCapacityNorStaysIsLeftOutAndCapacityAloneIsEnough(): void
    {
        self::bootKernel();
        self::branch('Mombasa', 6);

        $result = self::calculator()->calculate(new \DateTimeImmutable('2026-10-11'));

        self::assertSame(['Mombasa'], array_map(static fn(array $row): string => $row['branch']->getName(), $result));
        self::assertSame(array_fill(0, 12, 0), array_map(static fn(array $w): int => $w['peak'], $result[0]['weeks']));
        self::assertNull($result[0]['firstOver']);
    }

    #[Test]
    public function anInactiveBranchIsLeftOut(): void
    {
        self::bootKernel();
        $branch = self::branch('Mombasa', 6);
        self::stay($branch, '2026-10-05', '2026-10-30');
        $branch->setIsActive(false);
        save($branch);

        self::assertSame([], self::calculator()->calculate(new \DateTimeImmutable('2026-10-11')));
    }

    private static function branch(string $name, int $capacity): Branch
    {
        $branch = BranchFactory::find(['name' => $name]);
        $branch->setCapacity($capacity);
        save($branch);

        return $branch;
    }

    private static function stay(Branch $branch, string $start, string $end): void
    {
        StayFactory::createOne([
            'volunteer' => VolunteerFactory::new()->withoutStay(),
            'branch' => $branch,
            'startDate' => new \DateTimeImmutable($start),
            'endDate' => new \DateTimeImmutable($end),
        ]);
    }

    /** @return array{branch: Branch, capacity: ?int, weeks: list<array{start: \DateTimeImmutable, peak: int}>, firstOver: ?\DateTimeImmutable} */
    private static function rowFor(string $name): array
    {
        foreach (self::calculator()->calculate(new \DateTimeImmutable('2026-10-11')) as $row) {
            if ($name === $row['branch']->getName()) {
                return $row;
            }
        }
        self::fail("No row for $name.");
    }

    private static function calculator(): OccupancyLookAhead
    {
        return self::getContainer()->get(OccupancyLookAhead::class);
    }
}
