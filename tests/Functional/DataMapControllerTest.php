<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class DataMapControllerTest extends WebTestCase
{
    #[Test]
    public function aRegularRoleUserIsForbiddenFromTheDataMap(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => ['ROLE_USER']]));

        $client->request('GET', '/datamap');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function anAdminCanReadTheDataMap(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', '/datamap');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'What the application keeps');
        self::assertSelectorExists('a[href="/datamap"]');
    }
}
