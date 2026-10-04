<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The screen and its export; the rule itself is ProgramMatchFinderTest's.
 */
#[ResetDatabase]
final class MatchControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function aProgramListsAvailableVolunteersHoldingAnyNeededSkillMostMatchesFirst(): void
    {
        $client = static::createClient();
        [$plumbing, $painting, $cooking] = [
            SkillFactory::findOrCreate(['name' => 'Plumbing']),
            SkillFactory::findOrCreate(['name' => 'Painting']),
            SkillFactory::findOrCreate(['name' => 'Cooking']),
        ];
        $program = ProgramFactory::createOne(['skills' => [$plumbing, $painting]]);
        VolunteerFactory::createOne(['firstName' => 'One', 'lastName' => 'Match', 'skills' => [$painting, $cooking]]);
        VolunteerFactory::createOne(['firstName' => 'Two', 'lastName' => 'Matches', 'skills' => [$plumbing, $painting]]);
        VolunteerFactory::createOne(['firstName' => 'No', 'lastName' => 'Match', 'skills' => [$cooking]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertResponseIsSuccessful();
        self::assertNotCount(0, $crawler->filter('header a[href="/matches"]'), 'The nav names the screen.');
        self::assertCount(1, $crawler->filter('[data-match-explainer]'));
        $rows = self::rowsOf($crawler, (int) $program->getId());
        self::assertSame(
            [['Two Matches', 'Present', '2 of 2', 'Painting, Plumbing', '—', 'New to it'], ['One Match', 'Present', '1 of 2', 'Painting', 'Plumbing', 'New to it']],
            array_map(static fn(array $row): array => [$row[0], $row[1], $row[3], $row[4], $row[5], $row[6]], $rows),
        );

        // "New to it" is a badge on an empty cell: the export has the empty
        // cell, and the basis column says why the row is there.
        self::assertSame(
            array_map(static fn(array $row): array => [...array_slice($row, 0, 6), '', 'Skills'], $rows),
            array_map(static fn(array $row): array => [$row[3], $row[4], $row[5], $row[6], $row[8], $row[9], $row[10], $row[7]], self::exportedRows($client, '/matches/export.csv')),
        );
    }

    #[Test]
    public function aRowMatchedOnExperienceAloneSaysSoAndLinksToTheProfileToTickTheSkill(): void
    {
        $client = static::createClient();
        $football = ActivityTypeFactory::createOne(['name' => 'Football session']);
        $program = ProgramFactory::createOne(['skills' => [SkillFactory::findOrCreate(['name' => 'Plumbing'])], 'activityTypes' => [$football]]);
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Kept', 'lastName' => 'Coming']);
        // In a program of its own, with no skills: a match there is experience alone.
        $past = ActivityFactory::createOne(['volunteer' => $volunteer, 'activityType' => $football, 'date' => new \DateTimeImmutable('today')->modify('-3 months')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        $section = $crawler->filter(sprintf('[data-program-matches="%d"]', $program->getId()));
        $row = self::rowsOf($crawler, (int) $program->getId())[0];
        self::assertSame('0 of 1 By experience', $row[3]);
        self::assertSame('Plumbing Not on profile', $row[5]);
        self::assertStringStartsWith('Football session ×1 · last ', $row[6]);
        self::assertCount(1, $section->filter(sprintf('a[href="/volunteers/%d/edit"]', $volunteer->getId())));
        self::assertStringContainsString('Nobody available for: Plumbing', $section->filter('[data-uncovered]')->text());
        self::assertSame('By experience', self::rowsOf($crawler, (int) $past->getProgram()?->getId())[0][3], 'No skills to count: the badge alone, no dash.');
        $listed = $crawler->filter(sprintf('[data-no-skills] a[href="/programs/%d/edit#skills"]', $past->getProgram()?->getId()))->closest('li');
        self::assertStringContainsString('also shown above', (string) $listed?->text(), 'Skill-less, but with a block above.');
    }

    /**
     * Assign opens the batch form on a day that saves: today for someone here
     * now, the first day of the stay for someone arriving later.
     */
    #[Test]
    public function eachRowLinksToTheBatchFormPrefilledOnTheFirstDayTheVolunteerCanBeThere(): void
    {
        $client = static::createClient();
        $skill = SkillFactory::findOrCreate(['name' => 'Plumbing']);
        $program = ProgramFactory::createOne(['skills' => [$skill]]);
        $today = new \DateTimeImmutable('today');
        $here = VolunteerFactory::createOne(['skills' => [$skill]]);
        $arriving = VolunteerFactory::createOne(['skills' => [$skill], 'stays' => StayFactory::new([
            'startDate' => $today->modify('+10 days'),
            'endDate' => $today->modify('+40 days'),
        ])->many(1)]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        $assign = static fn(int $volunteerId, \DateTimeImmutable $date): string => sprintf('/activities/new?program=%d&volunteer=%d&date=%s&via=matches_skills', $program->getId(), $volunteerId, $date->format('Y-m-d'));
        self::assertSame(
            [$assign((int) $here->getId(), $today), $assign((int) $arriving->getId(), $today->modify('+10 days'))],
            $crawler->filter(sprintf('[data-program-matches="%d"] a', $program->getId()))->reduce(static fn(Crawler $link): bool => 'Assign' === trim($link->text()))->extract(['href']),
        );
    }

    /**
     * Already booked is still suggested: Booked lists the days, and Assign
     * opens on the first day left free.
     */
    #[Test]
    public function aBookedVolunteerStaysSuggestedAndAssignSkipsTheirBookedDays(): void
    {
        $client = static::createClient();
        $skill = SkillFactory::findOrCreate(['name' => 'Plumbing']);
        $program = ProgramFactory::createOne(['skills' => [$skill]]);
        $today = new \DateTimeImmutable('today');
        $volunteer = VolunteerFactory::createOne(['skills' => [$skill]]);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => $today]);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => $today->modify('+1 day')]);
        ActivityFactory::createOne(['volunteer' => $volunteer, 'date' => $today->modify('-1 day')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertSame(
            $today->format('j M') . ', ' . $today->modify('+1 day')->format('j M'),
            self::rowsOf($crawler, (int) $program->getId())[0][7],
            'From today on: yesterday is history, not a booking.',
        );
        self::assertSame(
            [sprintf('/activities/new?program=%d&volunteer=%d&date=%s&via=matches_skills', $program->getId(), $volunteer->getId(), $today->modify('+2 days')->format('Y-m-d'))],
            $crawler->filter(sprintf('[data-program-matches="%d"] a', $program->getId()))->reduce(static fn(Crawler $link): bool => 'Assign' === trim($link->text()))->extract(['href']),
        );
    }

    /**
     * The `via` tag is how /usage tells which kind of match got assigned.
     */
    #[Test]
    public function theAssignLinkIsTaggedWithWhyTheVolunteerMatched(): void
    {
        $client = static::createClient();
        $plumbing = SkillFactory::findOrCreate(['name' => 'Plumbing']);
        $football = ActivityTypeFactory::createOne(['name' => 'Football session']);
        $program = ProgramFactory::createOne(['skills' => [$plumbing], 'activityTypes' => [$football]]);
        $both = VolunteerFactory::createOne(['skills' => [$plumbing]]);
        $experienced = VolunteerFactory::createOne();
        foreach ([$both, $experienced] as $volunteer) {
            ActivityFactory::createOne(['volunteer' => $volunteer, 'activityType' => $football, 'date' => new \DateTimeImmutable('today')->modify('-3 months')]);
        }
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        $tags = $crawler->filter(sprintf('[data-program-matches="%d"] a', $program->getId()))
            ->reduce(static fn(Crawler $link): bool => 'Assign' === trim($link->text()))
            ->each(static fn(Crawler $link): string => (string) preg_replace('/.*via=/', '', (string) $link->attr('href')));
        self::assertSame(['matches_both', 'matches_experience'], $tags);
    }

    #[Test]
    public function theFiltersNarrowByBranchAndProgramAndIgnoreMalformedInput(): void
    {
        $client = static::createClient();
        $skill = SkillFactory::findOrCreate(['name' => 'Plumbing']);
        $nairobi = ProgramFactory::createOne(['skills' => [$skill]]);
        $mombasaBranch = BranchFactory::find(['name' => 'Mombasa']);
        $mombasa = ProgramFactory::createOne(['skills' => [$skill], 'project' => ProjectFactory::createOne(['branch' => $mombasaBranch])]);
        $client->loginUser(UserFactory::createOne());

        $sections = static fn(Crawler $crawler): array => $crawler->filter('[data-program-matches]')->each(static fn(Crawler $section): int => (int) $section->attr('data-program-matches'));

        self::assertSame([$mombasa->getId()], $sections($client->request('GET', '/matches?branch=' . $mombasaBranch->getId())));
        self::assertSame([$nairobi->getId()], $sections($client->request('GET', '/matches?program=' . $nairobi->getId())));
        self::assertCount(2, $sections($client->request('GET', '/matches?branch[]=1&program=abc&who=past')));
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function aProgramWithNeitherSkillsNorCandidatesIsOnlyInTheNoSkillsList(): void
    {
        $client = static::createClient();
        $program = ProgramFactory::createOne();
        VolunteerFactory::createOne(['skills' => [SkillFactory::findOrCreate(['name' => 'Plumbing'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-program-matches]'));
        $item = $crawler->filter(sprintf('[data-no-skills] a[href="/programs/%d/edit#skills"]', $program->getId()))->closest('li');
        self::assertStringNotContainsString('also shown above', (string) $item?->text());
        self::assertStringContainsString('Every volunteer with a current or upcoming stay has at least one skill', $crawler->filter('[data-volunteers-no-skills]')->text(), 'Shown even when empty.');
        self::assertSame([], self::exportedRows($client, '/matches/export.csv'));
    }

    #[Test]
    public function theNoSkillsListIsGroupedByBranchThenProject(): void
    {
        $client = static::createClient();
        $mombasa = ProjectFactory::createOne(['name' => 'Beach', 'branch' => BranchFactory::find(['name' => 'Mombasa'])]);
        [$zebra, $alpha] = [ProjectFactory::createOne(['name' => 'Zebra']), ProjectFactory::createOne(['name' => 'Alpha'])];
        ProgramFactory::createOne(['name' => 'Swim', 'project' => $mombasa]);
        ProgramFactory::createOne(['name' => 'Paint', 'project' => $zebra]);
        ProgramFactory::createOne(['name' => 'Read', 'project' => $alpha]);
        ProgramFactory::createOne(['name' => 'Write', 'project' => $alpha]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertStringContainsString('(4)', $crawler->filter('#no-skills-heading')->text());
        self::assertSame(
            [['Mombasa', ['Beach'], ['Swim']], ['Nairobi (HQ)', ['Alpha', 'Zebra'], ['Read', 'Write', 'Paint']]],
            $crawler->filter('[data-no-skills-branch]')->each(static fn(Crawler $branch): array => [
                $branch->filter('h3')->text(),
                $branch->filter('[data-no-skills-project]')->each(static fn(Crawler $project): string => $project->text()),
                $branch->filter('li a')->each(static fn(Crawler $program): string => $program->text()),
            ]),
            'Branches, then their projects, alphabetical.',
        );
    }

    #[Test]
    public function belowTheMatchesListsWhatToTickToGetMore(): void
    {
        $client = static::createClient();
        [$plumbing, $painting] = [SkillFactory::findOrCreate(['name' => 'Plumbing']), SkillFactory::findOrCreate(['name' => 'Painting'])];
        $none = ProgramFactory::createOne();
        $one = ProgramFactory::createOne(['skills' => [$plumbing]]);
        ProgramFactory::createOne(['skills' => [$plumbing, $painting]]);
        $today = new \DateTimeImmutable('today');
        $blank = VolunteerFactory::createOne(['firstName' => 'Ann', 'lastName' => 'Blank', 'stays' => [
            StayFactory::new(['startDate' => $today->modify('-5 days'), 'endDate' => $today->modify('+5 days')]),
            StayFactory::new(['branch' => BranchFactory::find(['name' => 'Mombasa']), 'startDate' => $today->modify('+20 days'), 'endDate' => $today->modify('+50 days')]),
        ]]);
        $arriving = VolunteerFactory::createOne(['firstName' => 'Ben', 'lastName' => 'Coming', 'stays' => StayFactory::new([
            'startDate' => $today->modify('+10 days'),
            'endDate' => $today->modify('+40 days'),
        ])->many(1)]);
        VolunteerFactory::new()->inactive()->create();
        VolunteerFactory::createOne(['skills' => [$painting]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertCount(1, $crawler->filter('[data-more-matches-explainer]'));
        $links = static fn(Crawler $crawler, string $list): array => $crawler->filter($list . ' a')->extract(['href']);
        self::assertSame([sprintf('/programs/%d/edit#skills', $none->getId())], $links($crawler, '[data-no-skills]'));
        self::assertSame([sprintf('/programs/%d/edit#skills', $one->getId())], $links($crawler, '[data-one-skill]'));
        $skillsRow = $client->request('GET', sprintf('/programs/%d/edit', $none->getId()))->filter('#skills');
        self::assertStringContainsString('Recommended Skills', $skillsRow->filter('label')->first()->text(), 'The anchor lands on the row, label included.');
        self::assertStringContainsString('Plumbing', $skillsRow->text(), 'The checkboxes to tick are in it.');
        $crawler = $client->request('GET', '/matches');
        self::assertStringContainsString('Plumbing', $crawler->filter('[data-one-skill] li')->text());
        self::assertSame(
            [sprintf('/volunteers/%d/edit', $blank->getId()), sprintf('/volunteers/%d/edit', $arriving->getId())],
            $links($crawler, '[data-volunteers-no-skills]'),
            'Current and future stays alike; neither the inactive one nor the one with a skill.',
        );
        $date = static fn(string $days): string => $today->modify($days)->format('j M Y');
        self::assertSame(
            sprintf('Ann Blank · Nairobi (HQ), here until %s · Mombasa, arrives %s', $date('+5 days'), $date('+20 days')),
            $crawler->filter('[data-volunteers-no-skills] li')->first()->text(),
            'Every stay, earliest first.',
        );
        self::assertStringContainsString('arrives ' . $date('+10 days'), $crawler->filter('[data-volunteers-no-skills] li')->last()->text());
        self::assertSame(['Present now (1)', 'Upcoming (1)'], $crawler->filter('[data-volunteers-no-skills-group]')->each(static fn(Crawler $h): string => $h->text()), 'Here now if a stay covers today, else upcoming.');

        $upcoming = $client->request('GET', '/matches?who=upcoming');
        self::assertSame(
            sprintf('Ann Blank · Mombasa, arrives %s', $date('+20 days')),
            $upcoming->filter('[data-volunteers-no-skills] li')->first()->text(),
            'The Who filter reaches the stays listed.',
        );
    }

    /**
     * @return list<list<string>>
     */
    private static function rowsOf(Crawler $crawler, int $programId): array
    {
        return $crawler->filter(sprintf('[data-program-matches="%d"] table tbody tr', $programId))
            ->each(static fn(Crawler $row): array => $row->filter('td')->each(static fn(Crawler $cell): string => trim(preg_replace('/\s+/', ' ', $cell->text()) ?? '')));
    }
}
