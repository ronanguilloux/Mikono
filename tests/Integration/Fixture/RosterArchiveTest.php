<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixture;

use App\Fixture\RosterArchive;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the committed roster extract itself.
 *
 * `docs/fixtures/rosters.yaml` is maintained by hand — a human transcribes
 * each new WhatsApp export into it (ADR 0012), which is exactly the kind of
 * pass that drops a name or misspells a site. These assertions are what turns
 * that into a failing test rather than a demo with a missing volunteer.
 */
final class RosterArchiveTest extends TestCase
{
    private static function archive(): RosterArchive
    {
        return RosterArchive::fromFile(\dirname(__DIR__, 3) . '/' . RosterArchive::DEFAULT_PATH);
    }

    #[Test]
    public function theCommittedArchiveParses(): void
    {
        $archive = self::archive();

        self::assertNotEmpty($archive->volunteers);
        self::assertNotEmpty($archive->escorts);
        self::assertNotEmpty($archive->projects);
        self::assertNotEmpty($archive->rosters);
    }

    #[Test]
    public function everyNameOnARosterIsDeclaredAtTheTopOfTheArchive(): void
    {
        $archive = self::archive();
        $volunteers = array_map(static fn($volunteer) => $volunteer->name, $archive->volunteers);

        foreach ($archive->rosters as $roster) {
            $day = $roster->date->format('Y-m-d');

            foreach ($roster->sites as $site) {
                foreach ($site->volunteers as $slot) {
                    self::assertContains($slot->name, $volunteers, "Volunteer \"{$slot->name}\" on {$day} is not in the volunteers list.");
                }

                foreach ($site->escorts as $escort) {
                    self::assertContains($escort, $archive->escorts, "Escort \"{$escort}\" on {$day} is not in the escorts list.");
                }
            }
        }
    }

    #[Test]
    public function noVolunteerIsRecordedTwiceUnderTheSameName(): void
    {
        $names = array_map(static fn($volunteer) => $volunteer->name, self::archive()->volunteers);

        self::assertSame(array_values(array_unique($names)), $names);
    }

    #[Test]
    public function theWholeArchiveShiftsOntoTheDayTheFixturesLoad(): void
    {
        $anchors = [];
        $anchored = [];
        foreach (self::archive()->rosters as $roster) {
            if (null !== $roster->anchor) {
                $anchors[] = $roster->anchor;
                $anchored[$roster->anchor] = $roster->date;
            }
        }

        // The home screen's two roster panels cover today and tomorrow; a
        // fixed archive date would leave both of them empty. Exactly one
        // roster carries each anchor, in that order — the list catches a
        // duplicate the map would silently overwrite.
        self::assertSame(['today', 'tomorrow'], $anchors);

        // One `anchor: today` offset moves the entire archive, so the pair has
        // to be two consecutive calendar days: a `tomorrow` any further out
        // leaves the home screen's tomorrow panel empty.
        self::assertSame(
            $anchored['today']->modify('+1 day')->format('Y-m-d'),
            $anchored['tomorrow']->format('Y-m-d'),
            '"anchor: tomorrow" must be the calendar day after "anchor: today".',
        );
    }

    /**
     * A transcription appends the newest roster to the end of the file, so an
     * out-of-order date is the tell that one was dropped into the wrong place.
     */
    #[Test]
    public function theArchiveRunsInDateOrder(): void
    {
        $dates = array_map(
            static fn($roster) => $roster->date->format('Y-m-d'),
            self::archive()->rosters,
        );

        $sorted = $dates;
        sort($sorted);

        self::assertSame($sorted, $dates);
        self::assertSame(array_values(array_unique($dates)), $dates, 'Two rosters share a date.');
    }

    #[Test]
    public function everyRosterCarriesAtLeastOneSiteWithAtLeastOneVolunteer(): void
    {
        foreach (self::archive()->rosters as $roster) {
            $day = $roster->date->format('Y-m-d');
            self::assertNotEmpty($roster->sites, "The roster for {$day} has no sites.");

            foreach ($roster->sites as $site) {
                // A site whose volunteers were cut off by WhatsApp's "Voir
                // plus" isn't a roster entry at all — it seeds nothing, so it
                // has no business being in the file.
                self::assertNotEmpty($site->volunteers, "Site \"{$site->projectKey}\" on {$day} has no volunteers.");
            }
        }
    }

    #[Test]
    public function theArchiveRecordsTheTwoEscortRosterThatDroveAdr0013(): void
    {
        $found = false;
        foreach (self::archive()->rosters as $roster) {
            foreach ($roster->sites as $site) {
                if (\count($site->escorts) > 1) {
                    $found = true;
                }
            }
        }

        self::assertTrue($found, 'The archive no longer contains a site with two escorts — the real case ADR 0013 exists for.');
    }
}
