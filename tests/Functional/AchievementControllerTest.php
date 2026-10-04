<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\AchievementFactory;
use App\Factory\BranchFactory;
use App\Factory\ProjectFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class AchievementControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function anAchievementAddedFromAStayShowsOnItsStayPage(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Nadia']);
        $stay = StayFactory::createOne([
            'volunteer' => $volunteer,
            'startDate' => new \DateTimeImmutable('2026-09-01'),
            'endDate' => new \DateTimeImmutable('2026-09-30'),
        ]);
        $project = ProjectFactory::createOne(['name' => 'Kibera Library']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/achievements/new");
        $client->submit($crawler->selectButton('Save')->form([
            'achievement_form[title]' => 'Building a library',
            'achievement_form[achievedOn]' => '2026-09-15',
            'achievement_form[project]' => (string) $project->getId(),
        ]));

        self::assertResponseRedirects("/stays/{$stay->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('[data-achievements]', 'Building a library');
        self::assertSelectorTextContains('[data-achievements]', '15 Sep 2026 · Kibera Library');
        AchievementFactory::assert()->count(1);
    }

    #[Test]
    public function anAchievementMustFallWithinItsStay(): void
    {
        $client = static::createClient();
        $stay = StayFactory::createOne([
            'startDate' => new \DateTimeImmutable('2026-09-01'),
            'endDate' => new \DateTimeImmutable('2026-09-30'),
        ]);
        $project = ProjectFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/achievements/new");
        $client->submit($crawler->selectButton('Save')->form([
            'achievement_form[title]' => 'Building a library',
            'achievement_form[achievedOn]' => '2026-10-01',
            'achievement_form[project]' => (string) $project->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choose a day within the stay, 1 Sep 2026 – 30 Sep 2026.');
        AchievementFactory::assert()->count(0);
    }

    #[Test]
    public function anAchievementsProjectMustBeAtTheStaysBranch(): void
    {
        $client = static::createClient();
        $stay = StayFactory::createOne();
        $project = ProjectFactory::createOne(['branch' => BranchFactory::find(['name' => 'Mombasa'])]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/achievements/new");
        $client->submit($crawler->selectButton('Save')->form([
            'achievement_form[title]' => 'Building a library',
            'achievement_form[achievedOn]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'achievement_form[project]' => (string) $project->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This project is at Mombasa, but the stay is at Nairobi (HQ).');
    }

    #[Test]
    public function anAchievementNeedsATitle(): void
    {
        $client = static::createClient();
        $stay = StayFactory::createOne();
        $project = ProjectFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/stays/{$stay->getId()}/achievements/new");
        $client->submit($crawler->selectButton('Save')->form([
            'achievement_form[achievedOn]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'achievement_form[project]' => (string) $project->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Say what was achieved.');
    }

    #[Test]
    public function editingAnAchievementUpdatesIt(): void
    {
        $client = static::createClient();
        $achievement = AchievementFactory::createOne(['title' => 'Building a library']);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/achievements/{$achievement->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form(['achievement_form[title]' => 'Opening a library']));

        self::assertResponseRedirects("/stays/{$achievement->getStay()?->getId()}");
        $client->followRedirect();
        self::assertSelectorTextContains('[data-achievements]', 'Opening a library');
    }

    #[Test]
    public function deleteRemovesAnAchievement(): void
    {
        $client = static::createClient();
        $achievement = AchievementFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$achievement->getStay()?->getVolunteer()?->getId()}");
        $client->submit($crawler->filter('[data-achievements]')->selectButton('Delete')->form());

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Achievement was deleted.');
        AchievementFactory::assert()->count(0);
    }

    #[Test]
    public function deleteIsRefusedWithoutAValidToken(): void
    {
        $client = static::createClient();
        $achievement = AchievementFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', "/achievements/{$achievement->getId()}/delete", ['_token' => 'forged']);

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid security token');
        AchievementFactory::assert()->count(1);
    }

    #[Test]
    public function deletingTheStayDeletesItsAchievements(): void
    {
        $client = static::createClient();
        $achievement = AchievementFactory::createOne();
        $volunteer = $achievement->getStay()?->getVolunteer();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$volunteer?->getId()}");
        $client->submit($crawler->filter('[data-stays]')->selectButton('Delete')->form());

        StayFactory::assert()->count(0);
        AchievementFactory::assert()->count(0);
    }

    #[Test]
    public function theVolunteerPageListsAchievementsNewestFirst(): void
    {
        $client = static::createClient();
        $stay = StayFactory::createOne([
            'startDate' => new \DateTimeImmutable('2026-01-01'),
            'endDate' => new \DateTimeImmutable('2026-12-31'),
        ]);
        AchievementFactory::createOne(['stay' => $stay, 'title' => 'Older', 'achievedOn' => new \DateTimeImmutable('2026-03-01')]);
        AchievementFactory::createOne(['stay' => $stay, 'title' => 'Newer', 'achievedOn' => new \DateTimeImmutable('2026-06-01')]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', "/volunteers/{$stay->getVolunteer()?->getId()}");

        self::assertStringContainsString('Newer', $crawler->filter('[data-achievements] li')->first()->text());
    }

    #[Test]
    public function theIndexListsAchievementsAndExportsThemWithoutTheDescription(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::new()->withoutStay()->create(['firstName' => 'Nadia', 'lastName' => 'Otieno']);
        $achievement = AchievementFactory::createOne([
            'stay' => StayFactory::new(['volunteer' => $volunteer]),
            'project' => ProjectFactory::new(['name' => 'Kibera Library']),
            'title' => 'Building a library',
            'description' => 'Private detail',
            'achievedOn' => $today = new \DateTimeImmutable('today'),
        ]);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/reports/achievements');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Each achievement belongs to a volunteer\'s stay');
        self::assertSelectorTextContains('table tbody', 'Building a library');
        self::assertSelectorExists("table tbody a[href=\"/stays/{$achievement->getStay()?->getId()}\"]");

        self::assertSame(
            [[$today->format('j M Y'), 'Building a library', 'Nadia Otieno', 'Kibera Library', 'Nairobi (HQ)']],
            self::exportedRows($client, '/reports/achievements/export.csv'),
        );
    }
}
