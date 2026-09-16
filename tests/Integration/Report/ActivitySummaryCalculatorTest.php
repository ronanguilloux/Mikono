<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Factory\ActivityFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Report\ActivitySummaryCalculator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivitySummaryCalculatorTest extends KernelTestCase
{
    #[Test]
    public function aGroupOnOneDayIsOneDayOnDutyWhateverItsSize(): void
    {
        self::bootKernel();
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $project = ProjectFactory::createOne();
        ActivityFactory::createMany(4, ['date' => new \DateTimeImmutable('2026-08-04'), 'project' => $project, 'escorts' => [$escort]]);

        $rows = $this->normalized();
        self::assertNotNull($rows[0]['mostRecentActivityId'] ?? null);
        unset($rows[0]['mostRecentActivityId']);
        self::assertEquals([[
            'id' => $escort->getId(),
            'label' => 'Mr Maeba',
            'count' => 4,
            'days' => 1,
            'outings' => 1,
            'mostRecent' => new \DateTimeImmutable('2026-08-04'),
        ]], $rows);
    }

    /**
     * Hassan covered two sites on 11 Aug 2026: one day, two site visits.
     */
    #[Test]
    public function twoSitesOnOneDayAreOneDayAndTwoSiteVisits(): void
    {
        self::bootKernel();
        $escort = EscortFactory::createOne(['name' => 'Hassan']);
        $date = new \DateTimeImmutable('2026-08-11');
        ActivityFactory::createOne(['date' => $date, 'escorts' => [$escort]]);
        ActivityFactory::createOne(['date' => $date, 'escorts' => [$escort]]);
        $latest = ActivityFactory::createOne(['date' => new \DateTimeImmutable('2026-08-12'), 'escorts' => [$escort]]);

        $row = $this->normalized()[0];
        self::assertSame([3, 2, 3], [$row['count'], $row['days'], $row['outings']]);
        self::assertEquals(new \DateTimeImmutable('2026-08-12'), $row['mostRecent']);
        self::assertSame($latest->getId(), $row['mostRecentActivityId']);
    }

    #[Test]
    public function bothEscortsOfAGroupAreCreditedAndAnEmptyListIsNotRecorded(): void
    {
        self::bootKernel();
        $edna = EscortFactory::createOne(['name' => 'Edna']);
        $sam = EscortFactory::createOne(['name' => 'Sam']);
        $date = new \DateTimeImmutable('2026-08-30');
        ActivityFactory::createOne(['date' => $date, 'escorts' => [$edna, $sam]]);
        ActivityFactory::createOne(['date' => $date->modify('+1 day'), 'escorts' => [$sam]]);
        ActivityFactory::createOne(['date' => $date, 'escorts' => []]);

        $rows = $this->normalized();

        // Sam's two days first; the two one-day rows keep the order the
        // repository met them in — newest date, then highest id, first.
        self::assertSame(['Sam', 'No escort recorded', 'Edna'], array_column($rows, 'label'));
        self::assertSame([$sam->getId(), null, $edna->getId()], array_column($rows, 'id'));
        self::assertSame([2, 1, 1], array_column($rows, 'days'));
    }

    #[Test]
    public function moreSiteVisitsBreakATieOnDays(): void
    {
        self::bootKernel();
        $date = new \DateTimeImmutable('2026-08-14');
        $light = EscortFactory::createOne(['name' => 'Light']);
        $busy = EscortFactory::createOne(['name' => 'Busy']);
        ActivityFactory::createOne(['date' => $date, 'escorts' => [$light]]);
        ActivityFactory::createMany(2, ['date' => $date, 'escorts' => [$busy]]);

        self::assertSame(['Busy', 'Light'], array_column($this->normalized(), 'label'));
    }

    /**
     * @return list<array{id: ?int, label: string, count: int, days: int, outings: int, mostRecent: ?\DateTimeImmutable, mostRecentActivityId: ?int}>
     */
    private function normalized(): array
    {
        $calculator = self::getContainer()->get(ActivitySummaryCalculator::class);
        self::assertInstanceOf(ActivitySummaryCalculator::class, $calculator);

        // The entity manager is cleared so the report reads what a fresh
        // request would, not the collections the factories left in memory.
        self::getContainer()->get('doctrine')->getManager()->clear();

        return $calculator->summarizeByEscort();
    }
}
