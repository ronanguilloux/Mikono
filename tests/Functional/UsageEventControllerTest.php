<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\UsageEvent;
use App\Enum\UsageEventName;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The trust boundary in front of usage_event: a browser hands this endpoint a
 * string, and only four strings may ever become a row.
 */
#[ResetDatabase]
final class UsageEventControllerTest extends WebTestCase
{
    #[Test]
    public function aKnownEventNameIsRecorded(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', '/usage/event', [
            'name' => 'roster_copied',
            '_token' => self::token($client),
        ]);

        self::assertResponseStatusCodeSame(204);

        $events = self::stored();
        self::assertCount(1, $events);
        self::assertSame(UsageEventName::RosterCopied, $events[0]->getName());
    }

    /**
     * The enum is the whitelist. An invented name must not become a row, and
     * must not become a 400 either — a noisy console during normal use is how
     * a measurement feature ends up switched off.
     */
    #[Test]
    public function anUnknownEventNameIsIgnoredQuietly(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', '/usage/event', [
            'name' => 'DROP TABLE usage_event',
            '_token' => self::token($client),
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertCount(0, self::stored());
    }

    #[Test]
    public function anEventWithoutAValidTokenIsNotRecorded(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', '/usage/event', ['name' => 'roster_copied', '_token' => 'nope']);

        self::assertResponseStatusCodeSame(204);
        self::assertCount(0, self::stored());
    }

    #[Test]
    public function recordingAnEventRequiresBeingLoggedIn(): void
    {
        $client = static::createClient();

        $client->request('POST', '/usage/event', ['name' => 'roster_copied']);

        self::assertResponseRedirects();
        self::assertCount(0, self::stored());
    }

    private static function token(KernelBrowser $client): string
    {
        // Rendered into the page by the templates that carry the controller,
        // so taking it from a real response is also a check that they do.
        $crawler = $client->request('GET', '/');

        return $crawler->filter('[data-usage-event-token-value]')->attr('data-usage-event-token-value') ?? '';
    }

    /** @return list<UsageEvent> */
    private static function stored(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        return $entityManager->getRepository(UsageEvent::class)->findAll();
    }
}
