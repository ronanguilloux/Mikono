<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Indicator;
use App\Factory\AchievementFactory;
use App\Factory\IndicatorFactory;
use App\Factory\StayFactory;
use App\Report\ImpactByMonthCalculator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ImpactByMonthCalculatorTest extends KernelTestCase
{
    /**
     * The window is the twelve calendar months ending with today's: the
     * first day of the first month is in, the day before it and the first
     * day of next month are out.
     */
    #[Test]
    public function itSumsEachIndicatorPerMonthOverTheTwelveMonthsEndingNow(): void
    {
        self::bootKernel();
        $children = IndicatorFactory::createOne(['name' => 'Children reached']);
        self::counted($children, '2025-11-01', 5);  // first day in
        self::counted($children, '2025-10-31', 99); // the day before: out
        self::counted($children, '2026-09-03', 10);
        self::counted($children, '2026-09-28', 15);
        self::counted($children, '2026-11-01', 99); // next month: out

        $result = self::calculator()->calculate(new \DateTimeImmutable('2026-10-11'));

        self::assertCount(1, $result);
        self::assertSame('Children reached', $result[0]['indicator']);
        self::assertSame(30, $result[0]['total']);
        self::assertCount(12, $result[0]['months']);
        self::assertSame('2025-11', $result[0]['months'][0]['month']->format('Y-m'));
        self::assertSame(5, $result[0]['months'][0]['total']);
        self::assertSame('2026-09', $result[0]['months'][10]['month']->format('Y-m'));
        self::assertSame(25, $result[0]['months'][10]['total']);
        self::assertSame('2026-10', $result[0]['months'][11]['month']->format('Y-m'));
        self::assertSame(0, $result[0]['months'][11]['total']);
    }

    /** Never one total across indicators; the biggest one charts first. */
    #[Test]
    public function indicatorsStayApartLargestTotalFirst(): void
    {
        self::bootKernel();
        self::counted(IndicatorFactory::createOne(['name' => 'Children reached']), '2026-10-01', 12);
        self::counted(IndicatorFactory::createOne(['name' => 'Meals served']), '2026-10-01', 40);
        IndicatorFactory::createOne(['name' => 'Trees planted']); // never counted: no chart

        $result = self::calculator()->calculate(new \DateTimeImmutable('2026-10-11'));

        self::assertSame([['Meals served', 40], ['Children reached', 12]], array_map(static fn(array $s) => [$s['indicator'], $s['total']], $result));
    }

    #[Test]
    public function anAchievementThatCountsNothingIsLeftOut(): void
    {
        self::bootKernel();
        AchievementFactory::createOne(['achievedOn' => new \DateTimeImmutable('2026-10-01')]);

        self::assertSame([], self::calculator()->calculate(new \DateTimeImmutable('2026-10-11')));
    }

    private static function counted(Indicator $indicator, string $day, int $quantity): void
    {
        $date = new \DateTimeImmutable($day);
        AchievementFactory::createOne([
            'stay' => StayFactory::new(['startDate' => $date, 'endDate' => $date]),
            'achievedOn' => $date,
            'indicator' => $indicator,
            'quantity' => $quantity,
        ]);
    }

    private static function calculator(): ImpactByMonthCalculator
    {
        return self::getContainer()->get(ImpactByMonthCalculator::class);
    }
}
