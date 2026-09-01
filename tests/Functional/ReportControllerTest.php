<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ReportControllerTest extends WebTestCase
{
    #[Test]
    public function aggregatesTotalDaysCorrectlyAcrossMultipleActivities(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Ronan', 'lastName' => 'Guilloux']);
        $project = ProjectFactory::createOne(['name' => 'Bright Achievers']);
        $activityType = ActivityTypeFactory::createOne();

        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'project' => $project,
            'activityType' => $activityType,
            'duration' => ActivityDuration::FullDay,
            'date' => new \DateTimeImmutable('2026-08-11'),
        ]);
        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'project' => $project,
            'activityType' => $activityType,
            'duration' => ActivityDuration::HalfDay,
            'date' => new \DateTimeImmutable('2026-08-18'),
        ]);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', '1.5');
        self::assertSelectorTextContains('body', '18 Aug 2026');
    }

    #[Test]
    public function showsAPlaceholderMessageWhenNoActivitiesExist(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No activities logged yet.');
    }

    #[Test]
    public function theKpiTilesReportHeadlineFigures(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['isActive' => true]);
        VolunteerFactory::createOne(['isActive' => false]);
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();
        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'project' => $project,
            'activityType' => $activityType,
            'duration' => ActivityDuration::FullDay,
            'date' => new \DateTimeImmutable('yesterday'),
        ]);
        ActivityFactory::createOne([
            'volunteer' => $volunteer,
            'project' => $project,
            'activityType' => $activityType,
            'duration' => ActivityDuration::HalfDay,
            'date' => new \DateTimeImmutable('tomorrow'),
        ]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        $tiles = $crawler->filter('[data-kpi-tiles] > div');
        self::assertCount(4, $tiles);
        self::assertStringContainsString('2', $tiles->eq(0)->text());
        self::assertStringContainsString('1 active', $tiles->eq(0)->text());
        self::assertStringContainsString('incl. 1 planned', $tiles->eq(2)->text());
        self::assertStringContainsString('1.5', $tiles->eq(3)->text());
    }

    #[Test]
    public function theTotalDaysTileFlagsActivitiesItCannotCount(): void
    {
        // ActivityDuration::Other carries an unparsed free-text duration, so
        // the total is knowingly short — the tile has to say so.
        $client = static::createClient();
        ActivityFactory::createOne([
            'duration' => ActivityDuration::Other,
            'durationOther' => 'two hours after school',
        ]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertStringContainsString('1 not counted', $crawler->filter('[data-kpi-tiles] > div')->eq(3)->text());
    }

    #[Test]
    public function theTopVolunteersCardRanksAtMostFiveByTotalDays(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne();
        $activityType = ActivityTypeFactory::createOne();

        // Six volunteers, descending days: the sixth must not appear.
        foreach (['Aiyana', 'Beatrice', 'Charles', 'Daniel', 'Esther', 'Faith'] as $index => $firstName) {
            $volunteer = VolunteerFactory::createOne(['firstName' => $firstName, 'lastName' => 'Otieno']);
            ActivityFactory::createMany(6 - $index, [
                'volunteer' => $volunteer,
                'project' => $project,
                'activityType' => $activityType,
                'duration' => ActivityDuration::FullDay,
            ]);
        }

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        $ranked = $crawler->filter('[aria-labelledby="top-volunteers-heading"] li');
        self::assertCount(5, $ranked);
        self::assertStringContainsString('Aiyana Otieno', $ranked->eq(0)->text());
        self::assertStringContainsString('6.0 days', $ranked->eq(0)->text());
        self::assertStringContainsString('Esther Otieno', $ranked->eq(4)->text());
        self::assertStringNotContainsString('Faith Otieno', $crawler->filter('[aria-labelledby="top-volunteers-heading"]')->text());
    }

    #[Test]
    public function thePrintButtonIsOfferedAndHiddenOnPaper(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        $button = $crawler->filter('button[onclick="window.print()"]');
        self::assertCount(1, $button);
        self::assertStringContainsString('print:hidden', (string) $button->attr('class'));
        self::assertStringContainsString('print:hidden', (string) $crawler->filter('header')->attr('class'));
    }

    #[Test]
    public function theBreakdownsAreTabbedAndDefaultToVolunteers(): void
    {
        $client = static::createClient();
        $this->summarised(['Ronan Guilloux'], ['Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        $active = $crawler->filter('[data-report-panel="screen"] nav a[aria-current="page"]');
        self::assertCount(1, $active);
        self::assertSame('By volunteer', trim($active->text()));
        self::assertStringContainsString('Ronan Guilloux', $this->screenPanel($crawler));
        self::assertStringNotContainsString('Bright Achievers', $this->screenPanel($crawler));
    }

    #[Test]
    public function theProjectTabShowsTheProjectBreakdown(): void
    {
        $client = static::createClient();
        $this->summarised(['Ronan Guilloux'], ['Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?tab=project');

        self::assertResponseIsSuccessful();
        self::assertSame('By project', trim($crawler->filter('[data-report-panel="screen"] nav a[aria-current="page"]')->text()));
        self::assertStringContainsString('Bright Achievers', $this->screenPanel($crawler));
        self::assertStringNotContainsString('Ronan Guilloux', $this->screenPanel($crawler));
    }

    #[Test]
    public function anUnknownTabFallsBackToVolunteersRatherThanFailing(): void
    {
        $client = static::createClient();
        $this->summarised(['Ronan Guilloux'], ['Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?tab=bogus');

        self::assertResponseIsSuccessful();
        self::assertSame('By volunteer', trim($crawler->filter('[data-report-panel="screen"] nav a[aria-current="page"]')->text()));
    }

    #[Test]
    public function switchingTabsStartsOverAtTheFirstPage(): void
    {
        $client = static::createClient();
        $this->summarised(['Ronan Guilloux'], ['Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?page=2&perPage=50');

        // Carrying `page` across a tab switch would drop the user on a page the
        // other breakdown may not even have.
        $href = (string) $crawler->filter('[data-report-panel="screen"] nav a')->eq(1)->attr('href');
        self::assertStringContainsString('tab=project', $href);
        self::assertStringContainsString('perPage=50', $href);
        self::assertStringNotContainsString('page=2', $href);
    }

    #[Test]
    public function theBreakdownShowsTwentyFiveRowsPerPageByDefault(): void
    {
        $client = static::createClient();
        $this->twentySixVolunteers();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertCount(25, $crawler->filter('[data-report-panel="screen"] table tbody tr'));
        self::assertCount(1, $crawler->filter('[data-report-panel="screen"] [data-pagination]'));
    }

    #[Test]
    public function theBreakdownPageSizeCanBeRaisedToFitEverything(): void
    {
        $client = static::createClient();
        $this->twentySixVolunteers();
        $client->loginUser(UserFactory::createOne());

        foreach (['perPage=50', 'perPage=all'] as $query) {
            $crawler = $client->request('GET', '/reports?' . $query);

            self::assertResponseIsSuccessful();
            self::assertCount(26, $crawler->filter('[data-report-panel="screen"] table tbody tr'), $query);
            // One page means no page links, only the size selector.
            self::assertCount(0, $crawler->filter('[data-report-panel="screen"] [data-pagination]'), $query);
        }
    }

    #[Test]
    public function anUnlistedPageSizeFallsBackToTwentyFive(): void
    {
        $client = static::createClient();
        $this->twentySixVolunteers();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?perPage=7');

        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('[data-report-panel="screen"] table tbody tr'));
    }

    #[Test]
    public function aPageBeyondTheLastShowsTheLastPageRatherThanAnError(): void
    {
        $client = static::createClient();
        $this->twentySixVolunteers();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?page=99');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-report-panel="screen"] table tbody tr'));
    }

    #[Test]
    public function aNonPositivePageShowsTheFirstRatherThanAnError(): void
    {
        $client = static::createClient();
        $this->twentySixVolunteers();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports?page=-3');

        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('[data-report-panel="screen"] table tbody tr'));
    }

    #[Test]
    public function thePrintPanelStillCarriesBothBreakdownsInFull(): void
    {
        // The print-friendly view shipped before the tabs did, handing over
        // every row of both tables. Tabbing the screen must not halve that.
        $client = static::createClient();
        $this->twentySixVolunteers();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        $printPanel = $crawler->filter('[data-report-panel="print"]');
        self::assertCount(2, $printPanel->filter('table'));
        self::assertCount(26, $printPanel->filter('table')->eq(0)->filter('tbody tr'));
        self::assertCount(26, $printPanel->filter('table')->eq(1)->filter('tbody tr'));

        self::assertStringContainsString('hidden print:block', (string) $printPanel->attr('class'));
        self::assertStringContainsString('print:hidden', (string) $crawler->filter('[data-report-panel="screen"]')->attr('class'));
    }

    #[Test]
    public function theBreakdownTableCarriesNoActionsColumn(): void
    {
        // These rows are read-only; DataTable's trailing actions column would
        // otherwise show up empty on every row.
        $client = static::createClient();
        $this->summarised(['Ronan Guilloux'], ['Bright Achievers']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertCount(4, $crawler->filter('[data-report-panel="screen"] table thead th'));
        self::assertCount(4, $crawler->filter('[data-report-panel="screen"] table tbody tr')->eq(0)->filter('td'));
    }

    #[Test]
    public function anEmptyBreakdownRendersNoPageLinksAtAll(): void
    {
        // Guards a real edge in the windowing math: at zero rows the page range
        // computes to [1, 0], which renders a link to page "0" unguarded.
        $client = static::createClient();

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-pagination]'));
        self::assertStringNotContainsString('page=0', (string) $client->getResponse()->getContent());
    }

    /**
     * @param list<string> $volunteerNames
     * @param list<string> $projectNames
     */
    private function summarised(array $volunteerNames, array $projectNames): void
    {
        $activityType = ActivityTypeFactory::createOne();
        foreach ($volunteerNames as $index => $fullName) {
            [$firstName, $lastName] = explode(' ', $fullName, 2);
            ActivityFactory::createOne([
                'volunteer' => VolunteerFactory::createOne(['firstName' => $firstName, 'lastName' => $lastName]),
                'project' => ProjectFactory::createOne(['name' => $projectNames[$index] ?? 'Some project']),
                'activityType' => $activityType,
                'duration' => ActivityDuration::FullDay,
            ]);
        }
    }

    /** Twenty-six volunteers on twenty-six projects — one row past a full page. */
    private function twentySixVolunteers(): void
    {
        $activityType = ActivityTypeFactory::createOne();
        for ($i = 1; $i <= 26; ++$i) {
            ActivityFactory::createOne([
                'volunteer' => VolunteerFactory::createOne(['firstName' => 'Volunteer', 'lastName' => sprintf('Number%02d', $i)]),
                'project' => ProjectFactory::createOne(['name' => sprintf('Project %02d', $i)]),
                'activityType' => $activityType,
                'duration' => ActivityDuration::FullDay,
            ]);
        }
    }

    private function screenPanel(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        return $crawler->filter('[data-report-panel="screen"]')->text();
    }
}
