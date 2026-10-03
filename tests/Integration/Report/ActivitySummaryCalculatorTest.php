<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\BeneficiaryGroupFactory;
use App\Factory\BranchFactory;
use App\Factory\EscortFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SourceFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
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
     * Counted by the stay's branch (ADR 0026), with the same duration rules as
     * every other total: a half day is 0.5, an "Other" duration counts as an
     * activity but adds no days. A branch with no activity has no row.
     */
    #[Test]
    public function branchTotalsFollowTheStayBranchAndTheDurationRules(): void
    {
        self::bootKernel();
        $nairobi = BranchFactory::find(['name' => 'Nairobi (HQ)']);
        $mombasa = BranchFactory::find(['name' => 'Mombasa']);
        $nairobiProject = ProjectFactory::createOne(['branch' => $nairobi]);
        $mombasaProject = ProjectFactory::createOne(['branch' => $mombasa]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::new()->withoutStay(), 'date' => new \DateTimeImmutable('2026-08-04'), 'project' => $mombasaProject, 'duration' => ActivityDuration::FullDay]);
        $latest = ActivityFactory::createOne(['volunteer' => VolunteerFactory::new()->withoutStay(), 'date' => new \DateTimeImmutable('2026-08-05'), 'project' => $mombasaProject, 'duration' => ActivityDuration::HalfDay]);
        ActivityFactory::createOne(['volunteer' => VolunteerFactory::new()->withoutStay(), 'date' => new \DateTimeImmutable('2026-08-06'), 'project' => $nairobiProject, 'duration' => ActivityDuration::Other, 'durationOther' => '2 hours']);

        $calculator = self::getContainer()->get(ActivitySummaryCalculator::class);
        self::assertInstanceOf(ActivitySummaryCalculator::class, $calculator);
        self::getContainer()->get('doctrine')->getManager()->clear();

        $rows = $calculator->summarizeByBranch();
        self::assertSame(['Mombasa', 'Nairobi (HQ)'], array_column($rows, 'label'));
        self::assertSame([$mombasa->getId(), $nairobi->getId()], array_column($rows, 'id'));
        self::assertSame([2, 1], array_column($rows, 'count'));
        self::assertSame([1.5, 0.0], array_column($rows, 'totalDays'));
        self::assertEquals(new \DateTimeImmutable('2026-08-05'), $rows[0]['mostRecent']);
        self::assertSame($latest->getId(), $rows[0]['mostRecentActivityId']);
    }

    /**
     * Each person once per row, planned activities included like the other
     * columns: a project counts distinct people across its programs, not the
     * sum of its programs' counts.
     */
    #[Test]
    public function volunteersEngagedCountsEachPersonOncePerRow(): void
    {
        self::bootKernel();
        $project = ProjectFactory::createOne(['name' => 'Olympic School']);
        $reading = ProgramFactory::createOne(['name' => 'Reading', 'project' => $project]);
        $maths = ProgramFactory::createOne(['name' => 'Maths', 'project' => $project]);
        $ada = VolunteerFactory::createOne();
        $bea = VolunteerFactory::createOne();
        ActivityFactory::createMany(2, ['volunteer' => $ada, 'program' => $reading, 'duration' => ActivityDuration::FullDay]);
        ActivityFactory::createOne(['volunteer' => $bea, 'program' => $reading, 'duration' => ActivityDuration::FullDay, 'date' => new \DateTimeImmutable('tomorrow')]);
        ActivityFactory::createOne(['volunteer' => $ada, 'program' => $maths, 'duration' => ActivityDuration::HalfDay]);

        $calculator = self::getContainer()->get(ActivitySummaryCalculator::class);
        self::assertInstanceOf(ActivitySummaryCalculator::class, $calculator);
        self::getContainer()->get('doctrine')->getManager()->clear();

        $programs = $calculator->summarizeByProgram();
        self::assertSame(['Reading', 'Maths'], array_column($programs, 'label'));
        self::assertSame(['Olympic School', 'Olympic School'], array_column($programs, 'parent'));
        self::assertSame([2, 1], array_column($programs, 'volunteers'));
        self::assertSame([2], array_column($calculator->summarizeByProject(), 'volunteers'));
    }

    /**
     * Like escorts, one activity counts in full under every group its program
     * serves; an untagged program's activities share one id-less bucket.
     */
    #[Test]
    public function everyGroupOfAProgramIsCreditedAndAnUntaggedOneIsNotRecorded(): void
    {
        self::bootKernel();
        $girls = BeneficiaryGroupFactory::createOne(['name' => 'Adolescent girls']);
        $pupils = BeneficiaryGroupFactory::createOne(['name' => 'Primary school pupils']);
        $mentoring = ProgramFactory::createOne(['beneficiaryGroups' => [$girls, $pupils]]);
        ActivityFactory::createMany(2, ['program' => $mentoring, 'duration' => ActivityDuration::FullDay]);
        ActivityFactory::createOne(['program' => ProgramFactory::createOne(), 'duration' => ActivityDuration::HalfDay]);

        $calculator = self::getContainer()->get(ActivitySummaryCalculator::class);
        self::assertInstanceOf(ActivitySummaryCalculator::class, $calculator);
        self::getContainer()->get('doctrine')->getManager()->clear();

        $rows = $calculator->summarizeByBeneficiaryGroup();
        usort($rows, static fn(array $a, array $b): int => $a['label'] <=> $b['label']);
        self::assertSame(['Adolescent girls', 'No group recorded', 'Primary school pupils'], array_column($rows, 'label'));
        self::assertSame([2, 1, 2], array_column($rows, 'count'));
        self::assertSame([2.0, 0.5, 2.0], array_column($rows, 'totalDays'));
        self::assertNull($rows[1]['id']);
    }

    /**
     * One year at a time (ADR 0041): that year's activities, credited in full
     * to every source of the volunteer. Every source has a row; no source is
     * 'Not recorded', and only when an activity lands there.
     */
    #[Test]
    public function sourcesCreditTheYearsWorkToEachSourceOfTheVolunteer(): void
    {
        self::bootKernel();
        $tikTok = SourceFactory::find(['name' => 'TikTok']);
        $volunteerWorld = SourceFactory::find(['name' => 'Volunteer World']);

        $aisha = VolunteerFactory::new()->withoutStay()->create(['sources' => [$tikTok]]);
        ActivityFactory::createOne(['volunteer' => $aisha, 'date' => new \DateTimeImmutable('2025-03-10'), 'duration' => ActivityDuration::FullDay]);
        ActivityFactory::createOne(['volunteer' => $aisha, 'date' => new \DateTimeImmutable('2025-03-11'), 'duration' => ActivityDuration::FullDay]);
        $baraka = VolunteerFactory::new()->withoutStay()->create(['sources' => [$tikTok, $volunteerWorld]]);
        ActivityFactory::createOne(['volunteer' => $baraka, 'date' => new \DateTimeImmutable('2025-06-02'), 'duration' => ActivityDuration::HalfDay]);
        // No source and no activity: here, but nowhere in the counts.
        $chausiku = VolunteerFactory::new()->withoutStay()->create();
        StayFactory::createOne(['volunteer' => $chausiku, 'startDate' => new \DateTimeImmutable('2024-12-20'), 'endDate' => new \DateTimeImmutable('2025-01-05')]);
        $emeka = VolunteerFactory::new()->withoutStay()->create();
        ActivityFactory::createOne(['volunteer' => $emeka, 'date' => new \DateTimeImmutable('2024-07-01'), 'duration' => ActivityDuration::HalfDay]);
        $dalia = VolunteerFactory::new()->withoutStay()->create(['sources' => [$tikTok]]);
        ActivityFactory::createOne(['volunteer' => $dalia, 'date' => new \DateTimeImmutable('2024-12-31'), 'duration' => ActivityDuration::FullDay]);

        $calculator = self::getContainer()->get(ActivitySummaryCalculator::class);
        self::assertInstanceOf(ActivitySummaryCalculator::class, $calculator);
        self::getContainer()->get('doctrine')->getManager()->clear();

        $rows = $calculator->summarizeBySource(2025);
        self::assertCount(7, $rows);
        self::assertSame(['TikTok', 'Volunteer World'], array_column(array_slice($rows, 0, 2), 'label'));
        $byLabel = array_column($rows, null, 'label');
        self::assertSame([3, 2.5, 2], [$byLabel['TikTok']['count'], $byLabel['TikTok']['totalDays'], $byLabel['TikTok']['volunteers']]);
        self::assertSame([1, 0.5, 1], [$byLabel['Volunteer World']['count'], $byLabel['Volunteer World']['totalDays'], $byLabel['Volunteer World']['volunteers']]);
        self::assertSame([0, 0.0, 0], [$byLabel['YouTube']['count'], $byLabel['YouTube']['totalDays'], $byLabel['YouTube']['volunteers']]);

        $byLabel = array_column($calculator->summarizeBySource(2024), null, 'label');
        self::assertSame([1, 1.0], [$byLabel['TikTok']['count'], $byLabel['TikTok']['totalDays']]);
        self::assertSame([1, 0.5, null], [$byLabel['Not recorded']['count'], $byLabel['Not recorded']['totalDays'], $byLabel['Not recorded']['id']]);
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
