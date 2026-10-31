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
    }
}
