<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\LoginAttempt;
use App\Entity\UsageEvent;
use App\Enum\UsageEventName;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The screen and its gate. What the numbers on it MEAN is asserted in
 * tests/Integration/Usage/AccessLogReaderTest.php, against a fixture.
 *
 * Deliberately nothing here asserts on the access-log table's contents or emptiness:
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
     * Unlike the access-log table, this one reads the test database, so its
     * contents can be asserted.
     */
    #[Test]
    public function theSignInsSectionListsAttemptsInTheRange(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new LoginAttempt('probe@example.org', false, '203.0.113.7'));
        $entityManager->flush();

        $crawler = $client->request('GET', '/usage');

        self::assertResponseIsSuccessful();
        $section = $crawler->filter('[data-sign-ins]')->text();
        self::assertStringContainsString('probe@example.org', $section);
        self::assertStringContainsString('203.0.113.7', $section);
        self::assertStringContainsString('90 days', $section);
    }

    #[Test]
    public function aKnownAccountLinksToItsUserAndAnUnknownOneIsFlagged(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $known = UserFactory::createOne(['email' => 'vm@example.org', 'fullName' => 'Zara Manager']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new LoginAttempt('vm@example.org', true, '10.0.0.1', new \DateTimeImmutable('-2 hours')));
        $entityManager->persist(new LoginAttempt('probe@example.org', false, '10.0.0.2', new \DateTimeImmutable('-1 hour')));
        $entityManager->flush();

        $crawler = $client->request('GET', '/usage');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('[data-sign-ins] tbody tr');
        // Most recent first by default.
        self::assertStringContainsString('probe@example.org', $rows->eq(0)->text());
        self::assertStringContainsString('Unknown account', $rows->eq(0)->text());
        self::assertSame('warning', $rows->eq(0)->attr('data-row-tone'));
        self::assertCount(0, $rows->eq(0)->filter('a'));

        $link = $rows->eq(1)->filter('a[href="/users/' . $known->getId() . '/edit"]');
        self::assertSame('Zara Manager', $link->text());
        self::assertStringNotContainsString('vm@example.org', $rows->eq(1)->text());
        self::assertNull($rows->eq(1)->attr('data-row-tone'));

        // Its own sort params, so the access-log table's `sort` is untouched.
        $crawler = $client->request('GET', '/usage?signInsSort=account&signInsDirection=desc');
        self::assertStringContainsString('Zara Manager', $crawler->filter('[data-sign-ins] tbody tr')->eq(0)->text());
    }

    #[Test]
    public function theInPageActionsTableSortsAndPagesOnItsOwnParams(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new UsageEvent(UsageEventName::RosterCopied));
        $entityManager->persist(new UsageEvent(UsageEventName::RosterCopied));
        $entityManager->persist(new UsageEvent(UsageEventName::RosterRevealed));
        $entityManager->flush();

        $crawler = $client->request('GET', '/usage');

        self::assertResponseIsSuccessful();
        // Most used first by default.
        self::assertStringContainsString('Roster copied to clipboard', $crawler->filter('[data-in-page-actions] tbody tr')->eq(0)->text());
        // Its own page-size param, so the access-log table's `perPage` is untouched.
        self::assertCount(1, $crawler->filter('[data-in-page-actions] select[name="eventsPerPage"]'));

        $crawler = $client->request('GET', '/usage?eventsSort=count&eventsDirection=asc');
        self::assertStringContainsString('Roster opened', $crawler->filter('[data-in-page-actions] tbody tr')->eq(0)->text());
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

    /**
     * In-page actions and Sign-ins sit below a long table: sorting or paging
     * them, or changing the range from wherever the controls end up, must not
     * send the reader back to the top. See ADR 0040.
     */
    #[Test]
    public function theUsageControlsKeepTheReadersPlace(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/usage');

        $controls = $crawler->filter('[data-in-page-actions] [data-sort-link], [data-in-page-actions] [data-pagination-bar] form, [data-sign-ins] [data-sort-link], [data-range-presets] a, [data-range-form]');
        self::assertGreaterThan(3, $controls->count());
        self::assertSame(
            array_fill(0, $controls->count(), 'replace'),
            $controls->each(static fn($control) => $control->attr('data-turbo-action')),
        );
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
    public function theUsageScreenIsReachableFromTheAdminMenuForAnAdminOnly(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $crawler = $client->request('GET', '/');
        self::assertCount(0, $crawler->filter('a[href="/usage"]'));
        self::assertCount(0, $crawler->filter('button:contains("Admin")'));

        $client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $client->request('GET', '/');
        self::assertGreaterThan(0, $crawler->filter('a[href="/usage"]')->count());
        self::assertCount(1, $crawler->filter('button:contains("Admin")'));
    }
}
