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
        $tiles = $crawler->filter('main .grid > div');
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

        self::assertStringContainsString('1 not counted', $crawler->filter('main .grid > div')->eq(3)->text());
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
}
