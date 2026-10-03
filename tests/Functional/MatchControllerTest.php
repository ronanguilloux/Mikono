<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
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
    public function aProgramWithNeitherSkillsNorCandidatesIsListedInOneNote(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne();
        VolunteerFactory::createOne(['skills' => [SkillFactory::findOrCreate(['name' => 'Plumbing'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/matches');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-program-matches]'));
        self::assertCount(1, $crawler->filter('[data-no-skills]'));
        self::assertSame([], self::exportedRows($client, '/matches/export.csv'));
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
