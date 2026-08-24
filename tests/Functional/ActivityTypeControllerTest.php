<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ActivityTypeControllerTest extends WebTestCase
{
    #[Test]
    public function new_with_valid_data_persists(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());
        $crawler = $client->request('GET', '/activity-types/new');

        $form = $crawler->selectButton('Save')->form([
            'activity_type_form[name]' => 'Computer lessons',
            'activity_type_form[description]' => 'Basic computer literacy for students',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/activity-types');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Computer lessons');
    }

    #[Test]
    public function delete_is_blocked_when_an_activity_references_the_type(): void
    {
        $client = static::createClient();
        $activityType = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);
        ActivityFactory::createOne(['activityType' => $activityType]);
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/activity-types');
        $client->submitForm('Delete');

        self::assertResponseRedirects('/activity-types');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cannot delete Computer lessons');
    }
}
