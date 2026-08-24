<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class UserControllerTest extends WebTestCase
{
    #[Test]
    public function aRegularRoleUserIsForbiddenFromTheUsersArea(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/users');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function anAdminCanAccessTheUsersArea(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/users');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function creatingAUserHashesThePasswordAndTheNewAccountCanLogIn(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/users/new');
        $form = $crawler->selectButton('Save')->form([
            'user_form[fullName]' => 'Grace Wanjiru',
            'user_form[email]' => 'grace@example.org',
            'user_form[roles]' => 'ROLE_USER',
            'user_form[plainPassword]' => 'a-real-password-123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/users');

        // Prove it's genuinely hashed and usable, not stored raw: log out
        // the admin and log in as the new account through the real form.
        $client->request('GET', '/logout');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'grace@example.org',
            '_password' => 'a-real-password-123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/reports');
    }

    #[Test]
    public function deactivatingAUserBlocksTheirNextLogin(): void
    {
        $client = static::createClient();
        $target = UserFactory::createOne(['email' => 'grace@example.org']);
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', "/users/{$target->getId()}/edit");
        $form = $crawler->selectButton('Save')->form([
            'user_form[fullName]' => $target->getFullName(),
            'user_form[email]' => 'grace@example.org',
            'user_form[roles]' => 'ROLE_USER',
        ]);
        $form['user_form[isActive]']->untick();
        $client->submit($form);

        $client->request('GET', '/logout');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'grace@example.org',
            '_password' => 'password-1234',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'deactivated');
    }

    #[Test]
    public function anAdminCannotDeleteTheirOwnAccount(): void
    {
        $client = static::createClient();
        $admin = UserFactory::new()->admin()->create();
        $client->loginUser($admin);

        $client->request('GET', '/users');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/users');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'cannot delete your own account');
    }
}
