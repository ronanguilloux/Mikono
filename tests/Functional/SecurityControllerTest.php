<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class SecurityControllerTest extends WebTestCase
{
    #[Test]
    public function unauthenticated_request_redirects_to_login(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function wrong_password_shows_an_error_and_does_not_authenticate(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'vm@example.org']);

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'vm@example.org',
            '_password' => 'not-the-right-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid credentials');
    }

    #[Test]
    public function correct_credentials_authenticate_and_land_on_reports(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'vm@example.org']);

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'vm@example.org',
            '_password' => 'password-1234',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/reports');
    }

    #[Test]
    public function deactivated_account_is_blocked_at_login(): void
    {
        $client = static::createClient();
        UserFactory::new()->inactive()->create(['email' => 'gone@example.org']);

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'gone@example.org',
            '_password' => 'password-1234',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'deactivated');
    }

    #[Test]
    public function logout_ends_the_session(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/logout');
        self::assertResponseRedirects('/login');

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }
}
