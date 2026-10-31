<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Factory\UserFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class OAuthFlowTest extends WebTestCase
{
    use UsesOAuthToken;

    private const string CHATGPT_REDIRECT = 'https://chatgpt.com/connector_platform_oauth_redirect';

    #[Test]
    public function aSignedInAdminCanConnectAndReadThenRefresh(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);

        $tokens = self::obtainTokens($client, UserFactory::new()->admin()->create());

        $client->request('GET', '/api/skills', server: self::bearer($tokens['access_token']));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'refresh_token' => $tokens['refresh_token'],
        ]);
        self::assertResponseIsSuccessful();

        // Rotated: a stolen refresh token stops working once it has been used.
        $client->request('POST', '/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'refresh_token' => $tokens['refresh_token'],
        ]);
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('invalid_grant', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function theSeededClientConnectsAnyAdminWithNoSecret(): void
    {
        $client = static::createClient();

        $tokens = self::obtainTokens($client, UserFactory::new()->admin()->create(), 'claude', null);

        $client->request('GET', '/api/skills', server: self::bearer($tokens['access_token']));
        self::assertResponseIsSuccessful();

        // Rotated, as a public client requires.
        $client->request('POST', '/token', ['grant_type' => 'refresh_token', 'client_id' => 'claude', 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/token', ['grant_type' => 'refresh_token', 'client_id' => 'claude', 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function theChatGptClientConnectsOnItsStableRedirectAndIsNamedInTheAudit(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $query = ['client_id' => 'chatgpt', 'redirect_uri' => self::CHATGPT_REDIRECT, 'resource' => 'https://localhost/mcp'] + self::authorizeQuery();

        $crawler = $client->request('GET', '/authorize', $query);
        self::assertSelectorTextContains('form', 'chatgpt.com');
        $client->submit($crawler->selectButton('Allow')->form());

        parse_str((string) parse_url((string) $client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $redirect);
        self::assertSame('https://localhost', $redirect['iss']);

        $client->request('POST', '/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'chatgpt',
            'redirect_uri' => self::CHATGPT_REDIRECT,
            'code' => $redirect['code'],
            'code_verifier' => self::VERIFIER,
            'resource' => 'https://localhost/mcp',
        ]);
        self::assertResponseIsSuccessful();
        /** @var array{access_token: string} $tokens */
        $tokens = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/skills', server: self::bearer($tokens['access_token']));
        self::assertResponseIsSuccessful();
        self::assertSame('chatgpt', self::getContainer()->get(Connection::class)->fetchOne('SELECT client FROM api_access'));
    }

    #[Test]
    public function aRefusedAuthorizationNamesTheIssuerToo(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/authorize', self::authorizeQuery('claude'));

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('error=access_denied', $location);
        self::assertStringContainsString('iss=https%3A%2F%2Flocalhost', $location);
    }

    #[Test]
    public function aConnectorStillSendingTheOldSecretKeepsWorking(): void
    {
        $client = static::createClient();

        $tokens = self::obtainTokens($client, UserFactory::new()->admin()->create(), 'claude', 'a-secret-from-before');

        $client->request('POST', '/token', ['grant_type' => 'refresh_token', 'client_id' => 'claude', 'client_secret' => 'a-secret-from-before', 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function thePublicClientRefusesASignInWithoutPkce(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $query = self::authorizeQuery('claude');
        unset($query['code_challenge'], $query['code_challenge_method']);

        $client->request('GET', '/authorize', $query);

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Code challenge must be provided', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function thePublicClientRefusesARedirectOutsideItsList(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/authorize', ['redirect_uri' => 'https://attacker.example/callback'] + self::authorizeQuery('claude'));

        self::assertResponseStatusCodeSame(401);
        self::assertStringNotContainsString('attacker.example', (string) $client->getResponse()->headers->get('Location'));
    }

    #[Test]
    public function theConsentScreenNamesWhereTheDataGoes(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/authorize', self::authorizeQuery());

        self::assertSelectorTextContains('form', 'claude.ai');
    }

    #[Test]
    public function denyingConsentIssuesNoCode(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/authorize', self::authorizeQuery());
        $client->submit($crawler->selectButton('Deny')->form());

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('error=access_denied', $location);
        self::assertStringNotContainsString('code=', $location);
    }

    #[Test]
    public function consentNeedsAValidCsrfToken(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('POST', '/authorize?' . http_build_query(self::authorizeQuery()), ['decision' => 'allow', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function aNonAdminIsRefusedAtConsent(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/authorize', self::authorizeQuery());

        self::assertStringContainsString('error=access_denied', (string) $client->getResponse()->headers->get('Location'));
    }

    #[Test]
    public function anAnonymousVisitorToAuthorizeSignsInFirst(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);

        $client->request('GET', '/authorize', self::authorizeQuery());

        self::assertResponseRedirects('/login');
        self::assertStringNotContainsString('iss=', (string) $client->getResponse()->headers->get('Location'));
    }

    #[Test]
    public function aDeactivatedUsersTokenStopsWorkingAtOnce(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $user = UserFactory::new()->admin()->create();
        $token = self::obtainTokens($client, $user)['access_token'];

        // SQL, because what matters is the row: the kernel has rebooted, so
        // $user is detached and a flush would write nothing.
        self::getContainer()->get(Connection::class)->update('`user`', ['is_active' => 0], ['id' => $user->getId()]);

        $client->request('GET', '/api/skills', server: self::bearer($token));
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function aDemotedAdminsTokenStopsWorkingAtOnce(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $user = UserFactory::new()->admin()->create();
        $token = self::obtainTokens($client, $user)['access_token'];

        self::getContainer()->get(Connection::class)->update('`user`', ['roles' => '["ROLE_USER"]'], ['id' => $user->getId()]);

        $client->request('GET', '/api/skills', server: self::bearer($token));
        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    #[DataProvider('protectedPaths')]
    public function noTokenGetsA401PointingAtDiscovery(string $path): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer resource_metadata="https://localhost/.well-known/oauth-protected-resource"');
    }

    /** @return iterable<array{string}> */
    public static function protectedPaths(): iterable
    {
        yield ['/api'];
        yield ['/api/volunteers'];
        yield ['/api/docs.jsonopenapi'];
        yield ['/api/reports/metrics'];
        yield ['/mcp'];
    }

    #[Test]
    public function aForgedTokenIsRefused(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/skills', server: self::bearer('not.a.token'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function aTokenCheckIsNotASignIn(): void
    {
        $client = static::createClient();
        self::createOAuthClient($client);
        $token = self::obtainTokens($client, UserFactory::new()->admin()->create())['access_token'];
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM login_attempt');

        $client->request('GET', '/api/skills', server: self::bearer($token));
        $client->request('GET', '/api/skills', server: self::bearer('not.a.token'));

        self::assertEquals(0, self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM login_attempt'));
    }

    #[Test]
    public function aSessionCannotReachTheApi(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        // A first request boots a fresh kernel, leaving only the session cookie:
        // loginUser() also puts its token straight into the token storage.
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/skills');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function discoveryIsPublicAndNamesTheMcpEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/.well-known/oauth-protected-resource');
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            (string) json_encode(['resource' => 'https://localhost/mcp', 'authorization_servers' => ['https://localhost'], 'scopes_supported' => ['read'], 'bearer_methods_supported' => ['header']]),
            (string) $client->getResponse()->getContent(),
        );

        $client->request('GET', '/.well-known/oauth-authorization-server');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"S256"', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('"none"', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('"authorization_response_iss_parameter_supported":true', (string) $client->getResponse()->getContent());
    }
}
