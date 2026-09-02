<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class DashboardControllerTest extends WebTestCase
{
    #[Test]
    public function showsTodaysRosterGroupedByProjectWithTheEscortLine(): void
    {
        $client = static::createClient();
        ActivityFactory::createOne([
            'date' => new \DateTimeImmutable('today'),
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Naomi', 'lastName' => 'Cherop']),
            'project' => ProjectFactory::createOne(['name' => 'UCESCO HQ']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'Orientation']),
            'escorts' => [EscortFactory::createOne(['name' => 'Mrs Achola'])],
        ]);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "Today's roster");
        self::assertSelectorTextContains('body', 'UCESCO HQ');
        self::assertSelectorTextContains('body', 'Orientation');
        self::assertSelectorTextContains('body', 'Naomi Cherop');
        self::assertSelectorTextContains('body', 'Accompanied by Mrs Achola');
    }

    #[Test]
    public function offersTomorrowsRosterAsACopyableWhatsAppMessage(): void
    {
        $client = static::createClient();
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day');
        ActivityFactory::createOne([
            'date' => $tomorrow,
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Grace', 'lastName' => 'Wanjiru']),
            'project' => ProjectFactory::createOne(['name' => 'Peggy Lucas school']),
            'activityType' => ActivityTypeFactory::createOne(['name' => 'School support']),
            'escorts' => [EscortFactory::createOne(['name' => 'Ms Njeri'])],
        ]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "Tomorrow's roster");

        $text = $crawler->filter('[data-clipboard-target="source"]')->text();
        self::assertStringContainsString('📍 Peggy Lucas school (School support)', $text);
        self::assertStringContainsString('- Grace Wanjiru', $text);
        self::assertStringContainsString('Accompanied by: Ms Njeri', $text);
        // The VM writes her own opening line — the app generates schedule only.
        self::assertStringNotContainsString('Good morning', $text);

        // "+ Plan activity" opens the batch form already dated tomorrow.
        self::assertSame(
            1,
            $crawler->filter(sprintf('a[href="/activities/new-batch?date=%s"]', $tomorrow->format('Y-m-d')))->count(),
        );
    }

    #[Test]
    public function listsQuietProjectsWithAWayToAssignVolunteersToThem(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Mtwapa Youth Centre']);
        ActivityFactory::createOne([
            'date' => (new \DateTimeImmutable('today'))->modify('-58 days'),
            'project' => $project,
        ]);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Projects needing volunteers');
        self::assertSelectorTextContains('body', 'Mtwapa Youth Centre');
        self::assertSelectorTextContains('body', '58 days');
        self::assertSame(
            1,
            $crawler->filter(sprintf('a[href="/activities/new-batch?project=%d"]', $project->getId()))->count(),
        );
    }

    #[Test]
    public function neverListsAVolunteerAsNeedingAttention(): void
    {
        // Volunteers come for a few weeks and then leave — one who has stopped
        // appearing has usually finished, not lapsed.
        $client = static::createClient();
        ActivityFactory::createOne([
            'date' => (new \DateTimeImmutable('today'))->modify('-70 days'),
            'volunteer' => VolunteerFactory::createOne(['firstName' => 'Long', 'lastName' => 'Gone', 'isActive' => true]),
        ]);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Long Gone');
    }

    #[Test]
    public function assignVolunteersOpensTheBatchFormOnThatProject(): void
    {
        $client = static::createClient();
        $project = ProjectFactory::createOne(['name' => 'Nyali Beach']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', sprintf('/activities/new-batch?project=%d', $project->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame(
            $project->getId(),
            (int) $crawler->filter('#batch_activity_form_project option[selected]')->attr('value'),
        );
    }

    #[Test]
    public function showsPlaceholdersWhenThereIsNothingToDo(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['fullName' => 'Ronan Guilloux']));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ronan Guilloux');
        self::assertSelectorTextContains('body', 'Nothing logged for today yet');
        self::assertSelectorTextContains('body', 'Nothing planned for tomorrow yet');
        self::assertSelectorTextContains('body', 'Every active project has been visited');
    }

    #[Test]
    public function requiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }
}
