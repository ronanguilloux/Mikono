<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\ProjectFactory;
use App\Factory\VolunteerFactory;
use App\Report\ReportMetricsCalculator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ReportMetricsCalculatorTest extends KernelTestCase
{
    #[Test]
    public function countsVolunteersAndProjectsBothInTotalAndActive(): void
    {
        self::bootKernel();
        VolunteerFactory::createMany(2, ['isActive' => true]);
        VolunteerFactory::createOne(['isActive' => false]);
        ProjectFactory::createMany(3, ['isActive' => true]);
        ProjectFactory::createOne(['isActive' => false]);

        $metrics = $this->calculator()->calculate(new \DateTimeImmutable('today'));

        self::assertSame(3, $metrics->volunteerCount);
        self::assertSame(2, $metrics->activeVolunteerCount);
        self::assertSame(4, $metrics->projectCount);
        self::assertSame(3, $metrics->activeProjectCount);
    }

    #[Test]
    public function countsOnlyFutureDatedActivitiesAsPlanned(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ActivityFactory::createOne(['date' => $today->modify('-1 day')]);
        ActivityFactory::createOne(['date' => $today]);
        ActivityFactory::createOne(['date' => $today->modify('+1 day')]);

        $metrics = $this->calculator()->calculate($today);

        self::assertSame(3, $metrics->activityCount);
        // Today's own activity has happened; only tomorrow's is planned.
        self::assertSame(1, $metrics->plannedActivityCount);
    }

    #[Test]
    public function sumsHalfAndFullDaysIntoTotalDays(): void
    {
        self::bootKernel();
        ActivityFactory::createOne(['duration' => ActivityDuration::FullDay]);
        ActivityFactory::createOne(['duration' => ActivityDuration::HalfDay]);
        ActivityFactory::createOne(['duration' => ActivityDuration::HalfDay]);

        $metrics = $this->calculator()->calculate(new \DateTimeImmutable('today'));

        self::assertSame(2.0, $metrics->totalDays);
        self::assertSame(0, $metrics->uncountedDurationCount);
        self::assertFalse($metrics->hasUncounted());
    }

    #[Test]
    public function reportsOtherDurationActivitiesAsUncountedWithoutInflatingTotalDays(): void
    {
        self::bootKernel();
        ActivityFactory::createOne(['duration' => ActivityDuration::FullDay]);
        ActivityFactory::createOne([
            'duration' => ActivityDuration::Other,
            'durationOther' => 'two hours after school',
        ]);

        $metrics = $this->calculator()->calculate(new \DateTimeImmutable('today'));

        self::assertSame(2, $metrics->activityCount);
        self::assertSame(1.0, $metrics->totalDays);
        self::assertSame(1, $metrics->uncountedDurationCount);
        self::assertTrue($metrics->hasUncounted());
    }

    #[Test]
    public function reportsZeroesWhenNothingHasBeenRecorded(): void
    {
        self::bootKernel();

        $metrics = $this->calculator()->calculate(new \DateTimeImmutable('today'));

        self::assertSame(0, $metrics->volunteerCount);
        self::assertSame(0, $metrics->activeVolunteerCount);
        self::assertSame(0, $metrics->projectCount);
        self::assertSame(0, $metrics->activeProjectCount);
        self::assertSame(0, $metrics->activityCount);
        self::assertSame(0, $metrics->plannedActivityCount);
        self::assertSame(0.0, $metrics->totalDays);
        self::assertFalse($metrics->hasUncounted());
    }

    private function calculator(): ReportMetricsCalculator
    {
        $calculator = self::getContainer()->get(ReportMetricsCalculator::class);
        self::assertInstanceOf(ReportMetricsCalculator::class, $calculator);

        return $calculator;
    }
}
