<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Enum\ActivityDuration;
use App\Factory\AchievementFactory;
use App\Factory\ActivityFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\VolunteerFactory;
use App\Report\ProjectOverviewCalculator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ProjectOverviewCalculatorTest extends KernelTestCase
{
    #[Test]
    public function countsTheWindowPerProjectAndKeepsPlannedWorkApart(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $project = ProjectFactory::createOne(['name' => 'Kibera Library']);
        $ada = VolunteerFactory::createOne();
        $ben = VolunteerFactory::createOne();

        $activity = static fn(mixed $volunteer, string $offset, ActivityDuration $duration) => ActivityFactory::createOne([
            'project' => $project,
            'volunteer' => $volunteer,
            'date' => $today->modify($offset),
            'duration' => $duration,
        ]);
        $activity($ada, '-1 day', ActivityDuration::FullDay);
        $activity($ada, '-2 days', ActivityDuration::HalfDay);
        $activity($ben, '-3 days', ActivityDuration::HalfDay);
        // Outside the twelve months, and still to come: neither is past work.
        $activity($ben, '-2 years', ActivityDuration::FullDay);
        $activity($ada, '+2 days', ActivityDuration::FullDay);

        $overview = self::getContainer()->get(ProjectOverviewCalculator::class)->calculate($today);

        self::assertCount(1, $overview['rows']);
        $row = $overview['rows'][0];
        self::assertSame('Planned', $row['status']);
        self::assertSame(2, $row['volunteers']);
        self::assertSame(2.0, $row['days']);
        self::assertSame(1, $row['booked']);
    }

    #[Test]
    public function sumsPlacesToFillClampedAtZeroAndIgnoresProgramsWithNoTarget(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $project = ProjectFactory::createOne();
        $full = ProgramFactory::createOne(['project' => $project, 'volunteersNeeded' => 1]);
        ActivityFactory::createMany(2, ['program' => $full, 'date' => $today->modify('+1 day'), 'volunteer' => VolunteerFactory::new()]);
        ProgramFactory::createOne(['project' => $project, 'volunteersNeeded' => 3]);
        ProgramFactory::createOne(['project' => $project, 'volunteersNeeded' => null]);

        $overview = self::getContainer()->get(ProjectOverviewCalculator::class)->calculate($today);

        self::assertSame(3, $overview['rows'][0]['placesToFill']);
        self::assertSame(3, $overview['rows'][0]['openPrograms']);
        self::assertSame(2, $overview['withTarget']);
    }

    #[Test]
    public function skipsInactiveProjectsAndCountsOnlyRecentAchievements(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ProjectFactory::createOne(['isActive' => false]);
        $project = ProjectFactory::createOne(['name' => 'Kept']);
        AchievementFactory::createOne(['project' => $project]);

        $overview = self::getContainer()->get(ProjectOverviewCalculator::class)->calculate($today);

        self::assertSame(['Kept'], array_column($overview['rows'], 'name'));
        self::assertSame('Never visited', $overview['rows'][0]['status']);
        self::assertSame(1, $overview['rows'][0]['achievements']);
        self::assertCount(12, $overview['months']);
    }
}
