<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProgramFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Repository\SkillRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class SkillControllerTest extends WebTestCase
{
    use ReadsListExports;

    /**
     * The list is seeded by its migration, which Foundry replays, so every
     * test starts with it — like Branch (ADR 0036).
     */
    #[Test]
    public function theSuggestedSkillsAreSeeded(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $names = array_column(self::exportedRows($client, '/skills/export.csv'), 0);

        self::assertCount(31, $names);
        self::assertContains('First aid', $names);
        self::assertSame('Art & crafts', $names[0]);
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/skills/new');

        $client->submit($crawler->selectButton('Save')->form([
            'skill_form[name]' => 'Plumbing',
            'skill_form[description]' => 'Pipes and taps',
        ]));

        self::assertResponseRedirects('/skills');
        self::assertSame('Pipes and taps', static::getContainer()->get(SkillRepository::class)->findOneBy(['name' => 'Plumbing'])?->getDescription());
    }

    #[Test]
    public function aDuplicateNameIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/skills/new');

        $client->submit($crawler->selectButton('Save')->form(['skill_form[name]' => 'First aid']));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function anUnusedSkillIsDeleted(): void
    {
        $client = static::createClient();
        SkillFactory::createOne(['name' => 'Plumbing']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/skills?perPage=100');
        $client->submit($crawler->filter('tr:contains("Plumbing")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/skills');
        self::assertNull(static::getContainer()->get(SkillRepository::class)->findOneBy(['name' => 'Plumbing']));
    }

    #[Test]
    public function deleteIsBlockedWhileAVolunteerHoldsTheSkill(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['skills' => [SkillFactory::createOne(['name' => 'Plumbing'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/skills?perPage=100');
        $client->submit($crawler->filter('tr:contains("Plumbing")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/skills');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Plumbing — 1 volunteer holds it.');
    }

    #[Test]
    public function deleteIsBlockedWhileAProgramNeedsTheSkill(): void
    {
        $client = static::createClient();
        ProgramFactory::createOne(['skills' => [SkillFactory::createOne(['name' => 'Plumbing'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/skills?perPage=100');
        $client->submit($crawler->filter('tr:contains("Plumbing")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/skills');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Plumbing — 1 program needs it.');
    }
}
