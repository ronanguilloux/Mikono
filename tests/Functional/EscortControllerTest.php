<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\EscortFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class EscortControllerTest extends WebTestCase
{
    #[Test]
    public function indexListsSeededEscorts(): void
    {
        $client = static::createClient();
        EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/escorts');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mr Maeba');
    }

    #[Test]
    public function newWithValidDataPersists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/escorts/new');

        $form = $crawler->selectButton('Save')->form([
            'escort_form[name]' => 'Mr Maeba',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/escorts');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Mr Maeba');
    }

    #[Test]
    public function editUpdatesTheEscort(): void
    {
        $client = static::createClient();
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', "/escorts/{$escort->getId()}/edit");

        $form = $crawler->selectButton('Save')->form([
            'escort_form[name]' => 'Mr Maeba Jr',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/escorts');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Mr Maeba Jr');
    }

    #[Test]
    public function deleteRemovesAnEscortWithNoActivities(): void
    {
        $client = static::createClient();
        EscortFactory::createOne(['name' => 'Mr Maeba']);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/escorts');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/escorts');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No escorts yet');
    }

    #[Test]
    public function deleteIsBlockedWhenAnActivityReferencesTheEscort(): void
    {
        $client = static::createClient();
        $escort = EscortFactory::createOne(['name' => 'Mr Maeba']);
        ActivityFactory::createOne(['escorts' => [$escort]]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/escorts');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/escorts');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Mr Maeba');
    }

    #[Test]
    public function theIndexPaginatesAtTwentyFivePerPage(): void
    {
        $client = static::createClient();
        EscortFactory::createMany(26);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/escorts');
        self::assertCount(25, $crawler->filter('table tbody tr'));

        $crawler = $client->request('GET', '/escorts?page=2');
        self::assertCount(1, $crawler->filter('table tbody tr'));
    }

    #[Test]
    public function theIndexSortsByARequestedColumn(): void
    {
        $client = static::createClient();
        EscortFactory::createOne(['name' => 'Mr Achieng']);
        EscortFactory::createOne(['name' => 'Mrs Zuma']);

        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/escorts?sort=name&direction=desc');
        self::assertStringContainsString('Mrs Zuma', $crawler->filter('table tbody tr')->first()->text());

        $crawler = $client->request('GET', '/escorts?sort=name&direction=asc');
        self::assertStringContainsString('Mr Achieng', $crawler->filter('table tbody tr')->first()->text());
    }

    #[Test]
    public function theIndexShrugsOffAnUnknownSortColumn(): void
    {
        $client = static::createClient();
        EscortFactory::createOne(['name' => 'Mr Achieng']);
        EscortFactory::createOne(['name' => 'Mrs Zuma']);

        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/escorts?sort=e.name&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mr Achieng', $crawler->filter('table tbody tr')->first()->text());
    }
}
