<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Branch;
use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Enum\PeriodStep;
use App\Factory\ActivityFactory;
use App\Factory\BranchFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\PeriodTotalsCalculator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * @phpstan-import-type PeriodRow from PeriodTotalsCalculator
 */
#[ResetDatabase]
final class PeriodTotalsCalculatorTest extends KernelTestCase
{
    /**
     * Present is a stay overlapping the month, whichever way it overlaps;
     * a stay that ends the day before or starts the day after does not.
     * Arrived is the month a stay starts. Each counts a volunteer once.
     */
    #[Test]
    public function presentIsAnOverlapAndArrivedIsAStartEachCountedOncePerVolunteer(): void
    {
        self::bootKernel();
        self::stay('2025-01-20', '2025-02-10'); // ends in February
        self::stay('2025-02-05', '2025-03-20'); // starts in February
        self::stay('2025-01-01', '2025-04-30'); // spans February
        self::stay('2025-02-10', '2025-02-20'); // starts and ends in February
        self::stay('2025-01-05', '2025-01-31'); // ends the day before
        self::stay('2025-03-01', '2025-03-10'); // starts the day after
        $twice = self::stay('2025-02-01', '2025-02-05')->getVolunteer();
        self::stay('2025-02-20', '2025-02-28', $twice);

        $months = $this->calculate('2025-03-15');

        self::assertSame(['2025-04', '2025-03', '2025-02', '2025-01'], array_keys($months));
        self::assertSame([3, 5, 3, 1], [$months['2025-01']['present'], $months['2025-02']['present'], $months['2025-03']['present'], $months['2025-04']['present']]);
        self::assertSame([3, 3, 1, 0], [$months['2025-01']['arrived'], $months['2025-02']['arrived'], $months['2025-03']['arrived'], $months['2025-04']['arrived']]);
    }

    /**
     * A change reaching before the first month is null; a quiet month inside
     * the range is a real zero to compare with.
     */
    #[Test]
    public function changesCompareWithThePreviousMonthAndTheSameMonthLastYear(): void
    {
        self::bootKernel();
        self::stay('2024-02-10', '2024-02-12');
        self::stay('2025-01-05', '2025-02-10');
        self::stay('2025-02-01', '2025-02-28');
        self::stay('2025-02-03', '2025-02-04');

        $months = $this->calculate('2025-02-15');

        self::assertSame('2025-02', array_key_first($months));
        self::assertSame('2024-02', array_key_last($months));
        self::assertSame([3, 2, 2], [$months['2025-02']['present'], $months['2025-02']['presentVsPrevious'], $months['2025-02']['presentVsLastYear']]);
        self::assertSame([1, 1], [$months['2025-02']['arrivedVsPrevious'], $months['2025-02']['arrivedVsLastYear']]);
        self::assertSame([1, null], [$months['2025-01']['presentVsPrevious'], $months['2025-01']['presentVsLastYear']]);
        self::assertNull($months['2024-02']['presentVsPrevious']);
    }

    /**
     * The range always reaches this month, even past the last stay, and
     * months to come are filled by upcoming stays.
     */
    #[Test]
    public function theRangeRunsFromTheFirstStayToTheLastAndIncludesThisMonth(): void
    {
        self::bootKernel();
        self::stay('2025-01-10', '2025-01-20');

        self::assertSame(['2025-03', '2025-02', '2025-01'], array_keys($this->calculate('2025-03-15')));

        self::stay('2025-05-01', '2025-05-31');
        self::assertSame(['2025-05', '2025-04', '2025-03', '2025-02', '2025-01'], array_keys($this->calculate('2025-03-15')));
    }

    /**
     * Engaged is the distinct volunteers with an activity in the month. A
     * branch narrows all three totals, an activity going by its stay's branch.
     */
    #[Test]
    public function engagedCountsVolunteersAndABranchNarrowsEveryTotal(): void
    {
        self::bootKernel();
        $mombasa = BranchFactory::createOne(['name' => 'Test Mombasa']);
        $atHq = self::stay('2025-02-01', '2025-02-28');
        self::activity($atHq, '2025-02-03');
        self::activity($atHq, '2025-02-04');
        $alsoAtHq = self::stay('2025-02-10', '2025-02-20');
        self::activity($alsoAtHq, '2025-02-11');
        $inMombasa = self::stay('2025-02-15', '2025-03-15', null, $mombasa);
        self::activity($inMombasa, '2025-03-01');

        $months = $this->calculate('2025-03-15');
        self::assertSame([2, 1], [$months['2025-02']['engaged'], $months['2025-03']['engaged']]);

        $months = $this->calculate('2025-03-15', $mombasa);
        self::assertSame([1, 1, 0], [$months['2025-02']['present'], $months['2025-02']['arrived'], $months['2025-02']['engaged']]);
        self::assertSame(1, $months['2025-03']['engaged']);
    }

    /**
     * By year, the same rules over calendar years: a stay over New Year is
     * present in both, arrives in the first, and the change against the
     * year before is the only comparison.
     */
    #[Test]
    public function byYearTheSameRulesCountCalendarYears(): void
    {
        self::bootKernel();
        $overNewYear = self::stay('2024-12-20', '2025-01-10');
        self::activity($overNewYear, '2025-01-02');
        self::stay('2025-03-01', '2025-03-31');
        self::stay('2025-06-01', '2025-06-30');

        $years = $this->calculate('2025-08-15', null, PeriodStep::Year);

        // PHP casts a numeric string key to int, so a year key reads back as one.
        self::assertSame([2025, 2024], array_keys($years));
        self::assertSame([1, 1, 0], [$years['2024']['present'], $years['2024']['arrived'], $years['2024']['engaged']]);
        self::assertSame([3, 2, 1], [$years['2025']['present'], $years['2025']['arrived'], $years['2025']['engaged']]);
        self::assertSame([2, 1, 1], [$years['2025']['presentVsPrevious'], $years['2025']['arrivedVsPrevious'], $years['2025']['engagedVsPrevious']]);
        self::assertSame($years['2025']['presentVsPrevious'], $years['2025']['presentVsLastYear']);
        self::assertNull($years['2024']['presentVsPrevious']);
    }

    /**
     * @return array<int|string, PeriodRow> keyed by period (a year key is an int), in the calculator's order
     */
    private function calculate(string $today, ?Branch $branch = null, PeriodStep $step = PeriodStep::Month): array
    {
        $calculator = self::getContainer()->get(PeriodTotalsCalculator::class);
        self::assertInstanceOf(PeriodTotalsCalculator::class, $calculator);
        self::getContainer()->get('doctrine')->getManager()->clear();

        $months = [];
        foreach ($calculator->calculate(new \DateTimeImmutable($today), $branch, $step) as $row) {
            $months[$step->key($row['period'])] = $row;
        }

        return $months;
    }

    private static function stay(string $start, string $end, ?Volunteer $volunteer = null, ?Branch $branch = null): Stay
    {
        return StayFactory::createOne(array_filter([
            'volunteer' => $volunteer ?? VolunteerFactory::new()->withoutStay(),
            'branch' => $branch,
            'startDate' => new \DateTimeImmutable($start),
            'endDate' => new \DateTimeImmutable($end),
        ]));
    }

    /** The stay is given, so the factory adds none of its own. */
    private static function activity(Stay $stay, string $date): void
    {
        ActivityFactory::createOne(['volunteer' => $stay->getVolunteer(), 'stay' => $stay, 'date' => new \DateTimeImmutable($date)]);
    }
}
