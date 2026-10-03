<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\SourceFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Repository\SourceRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class SourceControllerTest extends WebTestCase
{
    use ReadsListExports;

    /**
     * The list is seeded by its migration, which Foundry replays, so every
     * test starts with it — like Skill (ADR 0041).
     */
    #[Test]
    public function theVolunteerManagersSourcesAreSeeded(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $names = array_column(self::exportedRows($client, '/sources/export.csv'), 0);

        self::assertSame(['Facebook', 'Instagram', 'TikTok', 'Volunteer World', 'Website/Email', 'WhatsApp', 'YouTube'], $names);
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/sources/new');

        $client->submit($crawler->selectButton('Save')->form([
            'source_form[name]' => 'LinkedIn',
            'source_form[description]' => 'Posts and job ads',
        ]));

        self::assertResponseRedirects('/sources');
        self::assertSame('Posts and job ads', static::getContainer()->get(SourceRepository::class)->findOneBy(['name' => 'LinkedIn'])?->getDescription());
    }

    #[Test]
    public function aDuplicateNameIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/sources/new');

        $client->submit($crawler->selectButton('Save')->form(['source_form[name]' => 'TikTok']));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function anUnusedSourceIsDeleted(): void
    {
        $client = static::createClient();
        SourceFactory::createOne(['name' => 'LinkedIn']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/sources');
        $client->submit($crawler->filter('tr:contains("LinkedIn")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/sources');
        self::assertNull(static::getContainer()->get(SourceRepository::class)->findOneBy(['name' => 'LinkedIn']));
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableWhileAVolunteerHoldsTheSource(): void
    {
        $client = static::createClient();
        VolunteerFactory::createMany(2, ['sources' => [SourceFactory::createOne(['name' => 'LinkedIn'])]]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/sources');

        self::assertStringContainsString(
            'Cannot delete LinkedIn — 2 volunteers came through it.',
            $crawler->filter('tr:contains("LinkedIn") [aria-disabled="true"]')->text(),
        );
        self::assertCount(1, $crawler->filter('table tbody [aria-disabled="true"]'));
    }

    /**
     * The server-side guard holds even when the page was loaded before the
     * volunteer picked the source.
     */
    #[Test]
    public function deleteIsRefusedWhileAVolunteerHoldsTheSource(): void
    {
        $client = static::createClient();
        $linkedIn = SourceFactory::createOne(['name' => 'LinkedIn']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/sources');

        VolunteerFactory::createOne(['sources' => [$linkedIn]]);
        $client->submit($crawler->filter('tr:contains("LinkedIn")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/sources');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete LinkedIn — 1 volunteer came through it.');
        self::assertNotNull(static::getContainer()->get(SourceRepository::class)->findOneBy(['name' => 'LinkedIn']));
    }
}
