<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Entity\LoginAttempt;
use App\Entity\Volunteer;
use App\Factory\ActivityFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class UserControllerTest extends WebTestCase
{
    use ReadsListExports;

    #[Test]
    public function onlyAnAdminCanExportTheUsers(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/users/export.csv');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function theExportCarriesTheOnScreenSortOrEveryRowInDefaultOrder(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'zawadi@example.org', 'fullName' => 'Aisha Achieng']);
        $admin = UserFactory::new()->admin()->create(['email' => 'aisha@example.org', 'fullName' => 'Zawadi Zuma']);
        $client->loginUser($admin);

        $sorted = self::exportedRows($client, '/users/export.csv?sort=email&direction=asc');
        self::assertSame(['aisha@example.org', 'zawadi@example.org'], array_column($sorted, 1));

        $whole = self::exportedRows($client, '/users/export.csv');
        self::assertSame(['Aisha Achieng', 'Zawadi Zuma'], array_column($whole, 0));
        self::assertSame(['Volunteer Manager', 'Admin'], array_column($whole, 2));
    }

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

        self::assertResponseRedirects('/');
    }

    #[Test]
    public function thePasswordFieldCarriesAShowHideToggle(): void
    {
        $client = static::createClient();
        $target = UserFactory::createOne();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', "/users/{$target->getId()}/edit");

        $toggle = $crawler->filter('[data-controller="password-toggle"]');
        self::assertCount(1, $toggle);
        self::assertCount(1, $toggle->filter('input[type="password"][data-password-toggle-target="input"]'));
        self::assertSame('false', $toggle->filter('button[data-action="password-toggle#toggle"]')->attr('aria-pressed'));
        self::assertSame('Show password', str_replace("\u{a0}", ' ', $toggle->filter('button')->text()));
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

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        // 26 created here plus the admin doing the looking makes 27 — two on
        // the second page, not one.
        $client = static::createClient();
        UserFactory::createMany(26);
        $admin = UserFactory::new()->admin()->create();

        $client->loginUser($admin);

        $crawler = $client->request('GET', '/users');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/users?page=2');
        self::assertCount(2, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'aisha@example.org', 'fullName' => 'Aisha Achieng']);
        $admin = UserFactory::new()->admin()->create(['email' => 'zawadi@example.org', 'fullName' => 'Zawadi Zuma']);

        $client->loginUser($admin);

        $crawler = $client->request('GET', '/users?sort=email&direction=desc');
        self::assertStringContainsString('zawadi@example.org', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/users?sort=email&direction=asc');
        self::assertStringContainsString('aisha@example.org', $crawler->filter('table tbody tr')->first()->text());
    }

    /**
     * Role is derived from the `roles` JSON array via isAdmin(), not a column,
     * so there is nothing to ORDER BY and it stays out of the sort map.
     */
    #[Test]
    public function theRoleHeaderIsNotSortable(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', '/users');

        self::assertCount(1, $crawler->filter('[data-sort-link="name"]'));
        self::assertCount(0, $crawler->filter('[data-sort-link="role"]'));
        self::assertSame('Role', $crawler->filter('thead th')->eq(2)->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'aisha@example.org', 'fullName' => 'Aisha Achieng']);
        $admin = UserFactory::new()->admin()->create(['email' => 'zawadi@example.org', 'fullName' => 'Zawadi Zuma']);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/users?sort=role&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aisha Achieng', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function theUserPageListsWhatTheAccountDidNewestFirst(): void
    {
        $client = static::createClient();
        $vm = UserFactory::createOne(['email' => 'vm@example.org', 'fullName' => 'Zara Manager']);
        // Created before anyone signs in, so unattributed: only the edit below
        // is the VM's.
        $edited = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['loggedBy' => $vm, 'volunteer' => VolunteerFactory::createOne(['firstName' => 'Baraka', 'lastName' => 'Otieno'])]);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // Timestamps are stored to the second; push the setup into the past so
        // the edit is later than the creation and the order is deterministic.
        $entityManager->createQuery('UPDATE ' . Volunteer::class . ' v SET v.createdAt = :past')
            ->setParameter('past', new \DateTimeImmutable('-1 day'))
            ->execute();
        $entityManager->createQuery('UPDATE ' . Activity::class . ' a SET a.createdAt = :past')
            ->setParameter('past', new \DateTimeImmutable('-2 hours'))
            ->execute();
        $entityManager->persist(new LoginAttempt('VM@example.org', true, '10.0.0.1', new \DateTimeImmutable('-3 hours')));
        $entityManager->persist(new LoginAttempt('vm@example.org', false, '10.0.0.2', new \DateTimeImmutable('-4 hours')));
        $entityManager->persist(new LoginAttempt('probe@example.org', false, '203.0.113.7'));
        $entityManager->flush();

        $client->loginUser($vm);
        $crawler = $client->request('GET', '/volunteers/new');
        $client->submit($crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Grace',
            'volunteer_form[lastName]' => 'Wanjiru',
        ]));
        $crawler = $client->request('GET', "/volunteers/{$edited->getId()}/edit");
        $client->submit($crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Aisha',
            'volunteer_form[lastName]' => 'Njoroge',
            'volunteer_form[phone]' => '+254711111111',
        ]));

        $client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $client->request('GET', "/users/{$vm->getId()}");

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('[data-user-timeline] tbody tr')->each(static fn($row): string => $row->text());
        self::assertCount(5, $rows);
        // The first two happened in the same second; their order is not.
        $justNow = [$rows[0], $rows[1]];
        sort($justNow);
        self::assertStringContainsString('Added volunteer Grace Wanjiru', $justNow[0]);
        self::assertStringContainsString('Edited volunteer Aisha Njoroge', $justNow[1]);
        self::assertStringContainsString('Logged activity Baraka Otieno', $rows[2]);
        self::assertStringContainsString('Signed in from 10.0.0.1', $rows[3]);
        self::assertStringContainsString('Failed sign-in from 10.0.0.2', $rows[4]);
        self::assertSame('warning', $crawler->filter('[data-user-timeline] tbody tr')->eq(4)->attr('data-row-tone'));
        self::assertSelectorTextContains('[data-account-dates]', '1 activity logged');
    }

    #[Test]
    public function deletingAUserKeepsWhatTheyAddedWithoutAnAuthor(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne(['fullName' => 'Leaving Manager']);
        $client->loginUser($author);
        $crawler = $client->request('GET', '/volunteers/new');
        $client->submit($crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Grace',
            'volunteer_form[lastName]' => 'Wanjiru',
        ]));

        $client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $client->request('GET', '/users');
        $client->submit($crawler->filter("form[action=\"/users/{$author->getId()}/delete\"]")->form());

        self::assertResponseRedirects('/users');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Leaving Manager was deleted.');
        $authors = static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('SELECT IDENTITY(v.createdBy) AS createdBy, IDENTITY(v.updatedBy) AS updatedBy FROM ' . Volunteer::class . " v WHERE v.firstName = 'Grace'")
            ->getSingleResult();
        self::assertSame(['createdBy' => null, 'updatedBy' => null], $authors);
    }
}
