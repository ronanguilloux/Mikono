<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\LoginAttempt;
use App\Factory\UserFactory;
use App\Repository\LoginAttemptRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class SecurityControllerTest extends WebTestCase
{
    #[Test]
    public function unauthenticatedRequestRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function wrongPasswordShowsAnErrorAndDoesNotAuthenticate(): void
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
    public function correctCredentialsAuthenticateAndLandOnTheHomeScreen(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'vm@example.org']);

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'vm@example.org',
            '_password' => 'password-1234',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');
    }

    #[Test]
    public function deactivatedAccountIsBlockedAtLogin(): void
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
    public function aFailedLoginIsRecordedWithTheTypedEmailAndNeverThePassword(): void
    {
        $client = static::createClient();

        $this->submitLogin($client, 'nobody@example.org', 'secret-typed-here');

        $attempts = $this->attempts();
        self::assertCount(1, $attempts);
        self::assertSame('nobody@example.org', $attempts[0]->getIdentifier());
        self::assertFalse($attempts[0]->isSucceeded());
        self::assertNotNull($attempts[0]->getIp());
        self::assertStringNotContainsString('secret-typed-here', serialize($attempts[0]));
    }

    #[Test]
    public function aSuccessfulLoginIsRecorded(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'vm@example.org']);

        $this->submitLogin($client, 'vm@example.org', 'password-1234');

        $attempts = $this->attempts();
        self::assertCount(1, $attempts);
        self::assertSame('vm@example.org', $attempts[0]->getIdentifier());
        self::assertTrue($attempts[0]->isSucceeded());
    }

    /**
     * A password typed into the email box is the case this guards against.
     */
    #[Test]
    public function somethingThatIsNotAnEmailIsNotRecordedAsTheIdentifier(): void
    {
        $client = static::createClient();

        $this->submitLogin($client, 'hunter2-oops', 'whatever');

        $attempts = $this->attempts();
        self::assertCount(1, $attempts);
        self::assertNull($attempts[0]->getIdentifier());
    }

    #[Test]
    public function logoutEndsTheSession(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/logout');
        self::assertResponseRedirects('/login');

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    private function submitLogin(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => $password,
        ]));
    }

    /**
     * @return list<LoginAttempt>
     */
    private function attempts(): array
    {
        return static::getContainer()->get(LoginAttemptRepository::class)->findAll();
    }
}
