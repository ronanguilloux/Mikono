<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\AchievementFactory;
use App\Factory\IndicatorFactory;
use App\Factory\UserFactory;
use App\Repository\IndicatorRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class IndicatorControllerTest extends WebTestCase
{
    use ReadsListExports;

    /** Unseeded, like BeneficiaryGroup: the VM builds the list. */
    #[Test]
    public function theListStartsEmpty(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        self::assertSame([], self::exportedRows($client, '/indicators/export.csv'));
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/indicators/new');

        $client->submit($crawler->selectButton('Save')->form([
            'indicator_form[name]' => 'Children reached',
            'indicator_form[description]' => 'Distinct children in a session',
        ]));

        self::assertResponseRedirects('/indicators');
        self::assertSame('Distinct children in a session', static::getContainer()->get(IndicatorRepository::class)->findOneBy(['name' => 'Children reached'])?->getDescription());
    }

    #[Test]
    public function aDuplicateNameIsRefused(): void
    {
        $client = static::createClient();
        IndicatorFactory::createOne(['name' => 'Children reached']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/indicators/new');

        $client->submit($crawler->selectButton('Save')->form(['indicator_form[name]' => 'Children reached']));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function anUnusedIndicatorIsDeleted(): void
    {
        $client = static::createClient();
        IndicatorFactory::createOne(['name' => 'Meals served']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/indicators');
        $client->submit($crawler->filter('tr:contains("Meals served")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/indicators');
        self::assertNull(static::getContainer()->get(IndicatorRepository::class)->findOneBy(['name' => 'Meals served']));
    }

    #[Test]
    public function theIndexShowsDeleteAsUnavailableWhileAnAchievementCountsTheIndicator(): void
    {
        $client = static::createClient();
        AchievementFactory::createMany(2, ['indicator' => IndicatorFactory::createOne(['name' => 'Meals served']), 'quantity' => 40]);
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/indicators');

        self::assertStringContainsString(
            'Cannot delete Meals served — 2 achievements count it.',
            $crawler->filter('tr:contains("Meals served") [aria-disabled="true"]')->text(),
        );
    }

    /**
     * The server-side guard holds even when the page was loaded before the
     * achievement counted the indicator.
     */
    #[Test]
    public function deleteIsRefusedWhileAnAchievementCountsTheIndicator(): void
    {
        $client = static::createClient();
        $meals = IndicatorFactory::createOne(['name' => 'Meals served']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/indicators');

        AchievementFactory::createOne(['indicator' => $meals, 'quantity' => 40]);
        $client->submit($crawler->filter('tr:contains("Meals served")')->selectButton('Delete')->form());

        self::assertResponseRedirects('/indicators');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Meals served — 1 achievement counts it.');
        self::assertNotNull(static::getContainer()->get(IndicatorRepository::class)->findOneBy(['name' => 'Meals served']));
    }
}
