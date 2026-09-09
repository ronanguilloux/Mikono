<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The screen and its gate. What the numbers on it MEAN is asserted in
 * tests/Integration/Usage/AccessLogReaderTest.php, against a fixture.
 *
 * Deliberately nothing here asserts on the table's contents or emptiness:
 * %kernel.logs_dir% is var/log in the test environment too, so this reads the
 * dev container's real access.log locally and no file at all in CI. An
 * assertion either way would pass in one place and fail in the other.
 */
#[ResetDatabase]
final class UsageControllerTest extends WebTestCase
{
    #[Test]
    public function aRegularRoleUserIsForbiddenFromTheUsageScreen(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/usage');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function anAdminCanReadTheUsageScreen(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/usage');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Usage');
        // Whether there is a log or not, the screen says where its numbers
        // come from — that sentence is the answer to "is this tracking me?".
        self::assertStringContainsString('no third-party analytics', $crawler->filter('main')->text());
    }

    /**
     * The default range has to be visible on the control. A screen that opens
     * filtered without saying so reads as "nobody ever used this" when the
     * truth is "not in the last week".
     */
    #[Test]
    public function theDefaultDateRangeIsTickedOnABareUsageScreen(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/usage');

        self::assertResponseIsSuccessful();
        self::assertSame('7d', $crawler->filter('[data-range-presets] [aria-current]')->attr('data-range-preset'));
    }

    #[Test]
    public function aPresetLinkMovesTheActiveRange(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/usage?range=30d');

        self::assertResponseIsSuccessful();
        self::assertSame('30d', $crawler->filter('[data-range-presets] [aria-current]')->attr('data-range-preset'));
    }

    /**
     * Every query param this app reads degrades rather than erroring — there is
     * one non-technical user working from bookmarked URLs. InputBag::get()
     * throws on `?range[]=x` and getInt() on `?page=abc`, which is why neither
     * is used to read these.
     */
    #[Test]
    public function malformedFilterInputIsNotAnErrorScreen(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/usage?range[]=7d&from=abc&to[]=x&page=abc&perPage=nope');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function theUsageScreenIsReachableFromTheSettingsMenuForAnAdminOnly(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $crawler = $client->request('GET', '/');
        self::assertCount(0, $crawler->filter('a[href="/usage"]'));

        $client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $client->request('GET', '/');
        self::assertGreaterThan(0, $crawler->filter('a[href="/usage"]')->count());
    }
}
