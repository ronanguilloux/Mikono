<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\Functional\Api\UsesOAuthToken;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ConnectAiControllerTest extends WebTestCase
{
    use UsesOAuthToken;

    #[Test]
    #[DataProvider('pages')]
    public function aNonAdminCannotOpenAPage(string $path): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<array{string}> */
    public static function pages(): iterable
    {
        yield ['/connect-ai'];
        yield ['/connect-ai/readable'];
        yield ['/connect-ai/claude'];
        yield ['/connect-ai/chatgpt'];
        yield ['/connect-ai/gemini'];
    }

    #[Test]
    public function theMenuShowsItToAdminsOnly(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $client->request('GET', '/connect-ai');
        self::assertSelectorExists('header a[href="/connect-ai"]');

        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));
        $client->request('GET', '/');
        self::assertSelectorNotExists('header a[href="/connect-ai"]');
    }

    #[Test]
    public function theClaudePageGivesWhatToPasteAndNoSecret(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/connect-ai/claude');

        self::assertSelectorExists('input#mcp-url[value="https://localhost/mcp"]');
        self::assertSelectorExists('input#client-id[value="claude"]');
        self::assertSelectorTextContains('main, body', 'Leave the client secret empty');
    }

    #[Test]
    public function theChatGptPageGivesWhatToPasteAndWarnsAboutConsumerTerms(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/connect-ai/chatgpt');

        self::assertSelectorExists('input#mcp-url[value="https://localhost/mcp"]');
        self::assertSelectorExists('input#client-id[value="chatgpt"]');
        self::assertSelectorTextContains('main, body', 'Leave the client secret empty');
        self::assertSelectorTextContains('#account-heading', 'Free');
    }

    #[Test]
    public function theMapShowsWhatIsReadableAndWhatNeverIs(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/connect-ai/readable');

        // Every panel is in the HTML, so the list works without JavaScript.
        self::assertCount(15, $crawler->filter('[data-readable-map] [data-field-map-target="panel"]'));
        self::assertSelectorExists('[data-readable-map] svg .ent.tone-sensitive[data-id="volontaire"]');

        $volunteer = $crawler->filter('[data-field-map-target="panel"][data-id="volontaire"]')->text();
        self::assertStringContainsString('Emergency contacts (sensitive)', $volunteer);
        self::assertStringContainsString('Gender (sensitive)', $volunteer);
        self::assertStringContainsString('Not readable: Passport number', $volunteer);

        $never = $crawler->filter('[data-never-readable]')->text();
        self::assertStringContainsString('Volunteer: passport number', $never);
        self::assertStringContainsString('User: password', $never);
        self::assertStringContainsString('Login attempt', $never);
    }

    #[Test]
    public function theOverviewLinksToTheMapAndTheAiAssistants(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/connect-ai');

        self::assertSelectorExists('main a[href="/connect-ai/readable"], a[href="/connect-ai/readable"]');
        self::assertSelectorTextContains('#apps-heading', 'AI Assistants');
        self::assertSelectorNotExists('[data-readable-map]');
    }

    #[Test]
    public function aPageShowsWhetherYouAreConnected(): void
    {
        $client = static::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin);
        $client->request('GET', '/connect-ai');
        self::assertSelectorTextContains('[data-connection]', "isn't connected");

        self::obtainTokens($client, $admin, 'claude', null);
        $client->request('GET', '/connect-ai');

        self::assertSelectorTextContains('[data-connection]', 'Your account is connected');
    }

    #[Test]
    public function disconnectEndsYourTokensAndNobodyElses(): void
    {
        $client = static::createClient();
        $mine = self::obtainTokens($client, $admin = UserFactory::new()->admin()->create(), 'claude', null);
        $theirs = self::obtainTokens($client, UserFactory::new()->admin()->create(), 'claude', null);

        self::disconnect($client, $admin);

        $client->request('GET', '/api/skills', server: self::bearer($mine['access_token']));
        self::assertResponseStatusCodeSame(401);
        $client->request('POST', '/token', ['grant_type' => 'refresh_token', 'client_id' => 'claude', 'refresh_token' => $mine['refresh_token']]);
        self::assertResponseStatusCodeSame(400);

        $client->request('GET', '/api/skills', server: self::bearer($theirs['access_token']));
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function disconnectStillReachesARefreshTokenAfterTheNightlyPrune(): void
    {
        $client = static::createClient();
        $tokens = self::obtainTokens($client, $admin = UserFactory::new()->admin()->create(), 'claude', null);
        // An hour on: the access token has expired, the refresh token hasn't.
        self::getContainer()->get(Connection::class)->executeStatement("UPDATE oauth2_access_token SET expiry = '2000-01-01 00:00:00'");

        self::prune($client);
        self::disconnect($client, $admin);

        $client->request('POST', '/token', ['grant_type' => 'refresh_token', 'client_id' => 'claude', 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function pruneDeletesWhatNothingNeeds(): void
    {
        $client = static::createClient();
        self::obtainTokens($client, UserFactory::new()->admin()->create(), 'claude', null);
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement("UPDATE oauth2_access_token SET expiry = '2000-01-01 00:00:00'");
        $connection->executeStatement("UPDATE oauth2_refresh_token SET expiry = '2000-01-01 00:00:00'");

        self::prune($client);

        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM oauth2_refresh_token'));
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM oauth2_access_token'));
    }

    #[Test]
    public function disconnectRefusesAForgedToken(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('POST', '/connect-ai/disconnect', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    private static function disconnect(KernelBrowser $client, User $admin): void
    {
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/connect-ai');
        $client->submit($crawler->selectButton('Disconnect')->form());
        self::assertResponseRedirects('/connect-ai');
    }

    private static function prune(KernelBrowser $client): void
    {
        new CommandTester(new Application($client->getKernel())->find('app:oauth:prune'))->execute([]);
    }
}
