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
    public function aggregates_total_days_correctly_across_multiple_activities(): void
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
    public function shows_a_placeholder_message_when_no_activities_exist(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No activities logged yet.');
    }
}
