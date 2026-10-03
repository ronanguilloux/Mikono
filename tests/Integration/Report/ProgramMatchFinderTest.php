<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Branch;
use App\Entity\Skill;
use App\Entity\Volunteer;
use App\Enum\VolunteerStatus;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
use App\Factory\StayFactory;
use App\Factory\VolunteerFactory;
use App\Report\ProgramMatches;
use App\Report\ProgramMatchFinder;
use App\Report\VolunteerMatch;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The rule /matches explains in words (ADR 0042).
 */
#[ResetDatabase]
final class ProgramMatchFinderTest extends KernelTestCase
{
    #[Test]
    public function onlyAStayAtTheBranchThatHasNotEndedMakesSomeoneAvailable(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $mombasa = self::branch('Mombasa');
        $coaching = SkillFactory::findOrCreate(['name' => 'Coaching']);
        ProgramFactory::createOne(['name' => 'Football', 'project' => ProjectFactory::createOne(['branch' => $mombasa]), 'skills' => [$coaching]]);
        ProgramFactory::createOne(['name' => 'Ended', 'project' => ProjectFactory::createOne(['branch' => $mombasa]), 'skills' => [$coaching], 'startDate' => $today->modify('-2 months'), 'endDate' => $today->modify('-1 day')]);
        ProgramFactory::createOne(['name' => 'Closed project', 'project' => ProjectFactory::new()->inactive()->create(['branch' => $mombasa]), 'skills' => [$coaching]]);
        self::volunteer('Here', [$coaching], $mombasa, '-5 days', '+10 days');
        self::volunteer('Elsewhere', [$coaching], self::branch('Nairobi (HQ)'), '-5 days', '+10 days');
        self::volunteer('Gone', [$coaching], $mombasa, '-2 months', '-1 day');

        $result = $this->finder()->find($today);

        self::assertSame(['Football'], self::programNames($result));
        self::assertSame(['Here'], self::candidateNames($result[0]));
        self::assertTrue($result[0]->candidates[0]->present);
    }

    #[Test]
    public function theStayMustOverlapTheProgramsDates(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $mombasa = self::branch('Mombasa');
        $coaching = SkillFactory::findOrCreate(['name' => 'Coaching']);
        ProgramFactory::createOne(['project' => ProjectFactory::createOne(['branch' => $mombasa]), 'skills' => [$coaching], 'startDate' => $today->modify('+20 days')]);
        self::volunteer('Leaving', [$coaching], $mombasa, '-5 days', '+10 days');
        self::volunteer('Arriving', [$coaching], $mombasa, '+15 days', '+40 days');

        $result = $this->finder()->find($today);

        self::assertTrue($result[0]->notStarted);
        self::assertSame(['Arriving'], self::candidateNames($result[0]));
        self::assertFalse($result[0]->candidates[0]->present);
    }

    #[Test]
    public function pastActivityOfAnOfferedTypeMatchesBelowEverySkillMatch(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $hq = self::branch('Nairobi (HQ)');
        $coaching = SkillFactory::findOrCreate(['name' => 'Coaching']);
        $football = ActivityTypeFactory::createOne(['name' => 'Football session']);
        ProgramFactory::createOne(['project' => ProjectFactory::createOne(['branch' => $hq]), 'skills' => [$coaching], 'activityTypes' => [$football]]);
        $elsewhere = ProgramFactory::createOne(['project' => ProjectFactory::createOne(['branch' => self::branch('Mombasa')]), 'activityTypes' => [$football]]);
        self::volunteer('Skilled', [$coaching], $hq, '-5 days', '+10 days');
        $experienced = self::volunteer('Experienced', [], $hq, '-5 days', '+10 days');
        $other = self::volunteer('Other type', [], $hq, '-5 days', '+10 days');
        // Before their current stay, in a program at another branch.
        ActivityFactory::createOne(['volunteer' => $experienced, 'program' => $elsewhere, 'activityType' => $football, 'date' => $today->modify('-3 months')]);
        ActivityFactory::createOne(['volunteer' => $other, 'activityType' => ActivityTypeFactory::createOne(), 'date' => $today->modify('-3 months')]);
        // Planned, so not experience yet.
        ActivityFactory::createOne(['volunteer' => $other, 'program' => $elsewhere, 'activityType' => $football, 'date' => $today->modify('+3 months')]);

        $result = $this->finder()->find($today, self::branch('Nairobi (HQ)'));

        $matches = self::only($result, $coaching);
        self::assertSame(['Skilled', 'Experienced'], self::candidateNames($matches));
        $byExperience = $matches->candidates[1];
        self::assertTrue($byExperience->isByExperienceOnly());
        self::assertSame(['Football session' => 1], $byExperience->experience);
        self::assertSame(0, $byExperience->activitiesInProgram);
        self::assertEquals($today->modify('-3 months'), $byExperience->lastExperience);
        self::assertSame([$coaching], $byExperience->missingSkills);
        self::assertSame([], $matches->uncoveredSkills);
    }

    #[Test]
    public function aProgramWithoutSkillsStillFindsExperienceAndOneWithSkillsNamesWhatNobodyHolds(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $hq = self::branch('Nairobi (HQ)');
        $firstAid = SkillFactory::findOrCreate(['name' => 'First aid']);
        $football = ActivityTypeFactory::createOne(['name' => 'Football session']);
        $withSkills = ProgramFactory::createOne(['name' => 'With skills', 'project' => ProjectFactory::createOne(['branch' => $hq]), 'skills' => [$firstAid], 'activityTypes' => [$football]]);
        ProgramFactory::createOne(['name' => 'No skills', 'project' => ProjectFactory::createOne(['branch' => $hq]), 'activityTypes' => [$football]]);
        $experienced = self::volunteer('Experienced', [], $hq, '-5 days', '+10 days');
        ActivityFactory::createMany(2, ['volunteer' => $experienced, 'program' => $withSkills, 'activityType' => $football, 'date' => $today->modify('-3 months')]);

        [$noSkills, $skilled] = $this->finder()->find($today);

        self::assertSame('No skills', $noSkills->program->getName());
        self::assertSame(['Experienced'], self::candidateNames($noSkills));
        self::assertSame(0, $noSkills->candidates[0]->activitiesInProgram);
        self::assertSame(['Experienced'], self::candidateNames($skilled));
        self::assertSame(2, $skilled->candidates[0]->activitiesInProgram);
        self::assertSame([$firstAid], $skilled->uncoveredSkills);
    }

    #[Test]
    public function candidatesRankBySkillsThenExperienceThenPresenceThenName(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $hq = self::branch('Nairobi (HQ)');
        [$coaching, $firstAid] = [SkillFactory::findOrCreate(['name' => 'Coaching']), SkillFactory::findOrCreate(['name' => 'First aid'])];
        $football = ActivityTypeFactory::createOne(['name' => 'Football session']);
        $program = ProgramFactory::createOne(['project' => ProjectFactory::createOne(['branch' => $hq]), 'skills' => [$coaching, $firstAid], 'activityTypes' => [$football]]);
        self::volunteer('Upcoming', [$coaching], $hq, '+5 days', '+20 days');
        self::volunteer('Present B', [$coaching], $hq, '-5 days', '+10 days', 'B');
        self::volunteer('Present A', [$coaching], $hq, '-5 days', '+10 days', 'A');
        $experienced = self::volunteer('Experienced', [$coaching], $hq, '+5 days', '+20 days');
        ActivityFactory::createOne(['volunteer' => $experienced, 'program' => $program, 'activityType' => $football, 'date' => $today->modify('-3 months')]);
        self::volunteer('Both', [$coaching, $firstAid], $hq, '+5 days', '+20 days');

        $matches = $this->finder()->find($today)[0];

        self::assertSame(['Both', 'Experienced', 'Present A', 'Present B', 'Upcoming'], self::candidateNames($matches));
    }

    #[Test]
    public function whoFollowsTheStayThatMatchesNotTheVolunteersStatus(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        $mombasa = self::branch('Mombasa');
        $coaching = SkillFactory::findOrCreate(['name' => 'Coaching']);
        ProgramFactory::createOne(['project' => ProjectFactory::createOne(['branch' => $mombasa]), 'skills' => [$coaching]]);
        VolunteerFactory::createOne(['firstName' => 'Moving', 'skills' => [$coaching], 'stays' => [
            StayFactory::new(['branch' => self::branch('Nairobi (HQ)'), 'startDate' => $today->modify('-5 days'), 'endDate' => $today->modify('+5 days')]),
            StayFactory::new(['branch' => $mombasa, 'startDate' => $today->modify('+10 days'), 'endDate' => $today->modify('+30 days')]),
        ]]);

        self::assertSame([], $this->finder()->find($today, who: VolunteerStatus::Present)[0]->candidates);
        self::assertSame(['Moving'], self::candidateNames($this->finder()->find($today, who: VolunteerStatus::Upcoming)[0]));
    }

    #[Test]
    public function programsComeNeverRunFirstThenQuietestThenNotStartedYet(): void
    {
        self::bootKernel();
        $today = new \DateTimeImmutable('today');
        ProgramFactory::createOne(['name' => 'Later', 'startDate' => $today->modify('+10 days')]);
        ActivityFactory::createOne(['program' => ProgramFactory::createOne(['name' => 'Busy']), 'date' => $today->modify('-2 days')]);
        ActivityFactory::createOne(['program' => ProgramFactory::createOne(['name' => 'Planned']), 'date' => $today->modify('+3 days')]);
        ActivityFactory::createOne(['program' => ProgramFactory::createOne(['name' => 'Quiet']), 'date' => $today->modify('-40 days')]);
        ProgramFactory::createOne(['name' => 'Never']);

        $result = $this->finder()->find($today);

        self::assertSame(['Never', 'Quiet', 'Busy', 'Planned', 'Later'], self::programNames($result));
        self::assertNull($result[0]->daysSinceLastActivity);
        self::assertSame(40, $result[1]->daysSinceLastActivity);
        self::assertSame(-3, $result[3]->daysSinceLastActivity);
    }

    /**
     * @param list<Skill> $skills
     */
    private static function volunteer(string $firstName, array $skills, Branch $branch, string $from, string $to, ?string $lastName = null): Volunteer
    {
        $today = new \DateTimeImmutable('today');

        return VolunteerFactory::createOne([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'skills' => $skills,
            'stays' => [StayFactory::new(['branch' => $branch, 'startDate' => $today->modify($from), 'endDate' => $today->modify($to)])],
        ]);
    }

    private static function branch(string $name): Branch
    {
        return BranchFactory::find(['name' => $name]);
    }

    /**
     * @param list<ProgramMatches> $result
     */
    private static function only(array $result, Skill $skill): ProgramMatches
    {
        $found = array_values(array_filter($result, static fn(ProgramMatches $matches): bool => $matches->program->getSkills()->contains($skill)));
        self::assertCount(1, $found);

        return $found[0];
    }

    /**
     * @param list<ProgramMatches> $result
     *
     * @return list<string>
     */
    private static function programNames(array $result): array
    {
        return array_map(static fn(ProgramMatches $matches): string => $matches->program->getName(), $result);
    }

    /** @return list<string> */
    private static function candidateNames(ProgramMatches $matches): array
    {
        return array_map(static fn(VolunteerMatch $match): string => $match->volunteer->getFirstName(), $matches->candidates);
    }

    private function finder(): ProgramMatchFinder
    {
        $finder = self::getContainer()->get(ProgramMatchFinder::class);
        self::assertInstanceOf(ProgramMatchFinder::class, $finder);

        return $finder;
    }
}
