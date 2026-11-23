<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Every form field that picks from a Settings list carries a "⚙ Manage" link
 * to that list (ManageLinkTypeExtension). The achievement form is covered in
 * AchievementControllerTest; this walks the rest, new and edit alike.
 */
#[ResetDatabase]
final class ManageLinkTest extends WebTestCase
{
    #[Test]
    public function everyFieldPickingFromASettingsListLinksToIt(): void
    {
        $client = static::createClient();
        $activity = ActivityFactory::createOne();
        $stay = StayFactory::createOne();
        $volunteerId = $stay->getVolunteer()?->getId();
        $program = $activity->getProgram();
        $client->loginUser(UserFactory::createOne());

        $activityLinks = ['/activity-types', '/escorts', '/programs'];
        $volunteerLinks = ['/skills', '/sources'];
        $programLinks = ['/activity-types', '/beneficiary-groups', '/projects', '/skills'];
        $expected = [
            '/activities/new' => $activityLinks,
            "/activities/{$activity->getId()}/edit" => $activityLinks,
            '/volunteers/new' => $volunteerLinks,
            "/volunteers/{$volunteerId}/edit" => $volunteerLinks,
            '/projects/new' => ['/branches'],
            "/projects/{$program?->getProject()?->getId()}/edit" => ['/branches'],
            '/programs/new' => $programLinks,
            "/programs/{$program?->getId()}/edit" => $programLinks,
            "/volunteers/{$volunteerId}/stays/new" => ['/branches'],
            "/stays/{$stay->getId()}/edit" => ['/branches'],
            // Nothing on a branch picks from another list.
            '/branches/new' => [],
        ];

        foreach ($expected as $path => $hrefs) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful($path);

            $links = $crawler->filter('a[data-manage-link]');
            $found = $links->each(static fn($link) => (string) $link->attr('href'));
            sort($found);
            self::assertSame($hrefs, $found, $path);

            self::assertSame(array_fill(0, \count($hrefs), '_blank'), $links->each(static fn($link) => $link->attr('target')), $path);
            self::assertCount(0, $crawler->filter('label a[data-manage-link]'), $path);

            // Each row names a field the page really has, or the refresh
            // controller would look for an element that isn't there.
            $crawler->filter('[data-controller~="manage-link"]')->each(function ($row) use ($crawler, $path): void {
                $fieldId = (string) $row->attr('data-manage-link-field-value');
                self::assertCount(1, $crawler->filter('#' . $fieldId), "{$path} #{$fieldId}");
            });
        }
    }
}
