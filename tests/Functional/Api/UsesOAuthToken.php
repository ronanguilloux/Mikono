<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Gets a token the way Claude does, through the real client command, login,
 * consent screen and code exchange: a minted token would skip everything these
 * tests are here to prove.
 */
trait UsesOAuthToken
{
    private const string CLIENT_ID = 'claude-test';
    private const string CLIENT_SECRET = 'test-secret';
    private const string REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';
    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXkdBjftJeZ4CVP';

    private static function createOAuthClient(KernelBrowser $client): void
    {
        $tester = new CommandTester(new Application($client->getKernel())->find('league:oauth2-server:create-client'));
        $tester->execute([
            'name' => 'Claude',
            'identifier' => self::CLIENT_ID,
            'secret' => self::CLIENT_SECRET,
            '--redirect-uri' => [self::REDIRECT_URI],
            '--grant-type' => ['authorization_code', 'refresh_token'],
            '--scope' => ['read'],
        ]);
        $tester->assertCommandIsSuccessful();
    }

    /** @return array<string, string> */
    private static function authorizeQuery(): array
    {
        return [
            'response_type' => 'code',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'read',
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private static function obtainTokens(KernelBrowser $client, User $user): array
    {
        $client->loginUser($user);
        $crawler = $client->request('GET', '/authorize', self::authorizeQuery());
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Allow')->form());
        self::assertResponseStatusCodeSame(302);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::REDIRECT_URI, $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $redirect);

        $client->request('POST', '/token', [
            'grant_type' => 'authorization_code',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $redirect['code'],
            'code_verifier' => self::VERIFIER,
        ]);
        self::assertResponseIsSuccessful();

        /** @var array{access_token: string, refresh_token: string} $tokens */
        $tokens = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $tokens;
    }

    /** @return array<string, string> */
    private static function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_ACCEPT' => 'application/ld+json'];
    }
}
