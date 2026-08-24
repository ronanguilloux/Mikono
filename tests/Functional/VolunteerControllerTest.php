<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class VolunteerControllerTest extends WebTestCase
{
    #[Test]
    public function index_lists_seeded_volunteers(): void
    {
        $client = static::createClient();
        VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }

    #[Test]
    public function new_with_valid_data_persists_and_redirects(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Grace',
            'volunteer_form[lastName]' => 'Wanjiru',
            'volunteer_form[email]' => 'grace@example.org',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Grace Wanjiru');
    }

    #[Test]
    public function new_with_invalid_data_is_unprocessable_and_reshows_errors(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/volunteers/new');

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => '',
            'volunteer_form[lastName]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function edit_updates_the_volunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge', 'phone' => '+254700000000']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/volunteers/{$volunteer->getId()}/edit");

        $form = $crawler->selectButton('Save')->form([
            'volunteer_form[firstName]' => 'Aisha',
            'volunteer_form[lastName]' => 'Njoroge',
            'volunteer_form[phone]' => '+254711111111',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', '+254711111111');
    }

    #[Test]
    public function delete_removes_a_volunteer_with_no_activities(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No volunteers yet');
    }

    #[Test]
    public function delete_is_blocked_when_an_activity_references_the_volunteer(): void
    {
        $client = static::createClient();
        $volunteer = VolunteerFactory::createOne(['firstName' => 'Aisha', 'lastName' => 'Njoroge']);
        ActivityFactory::createOne(['volunteer' => $volunteer]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/volunteers');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/volunteers');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Aisha Njoroge');
        self::assertSelectorTextContains('body', 'Aisha Njoroge');
    }
}
