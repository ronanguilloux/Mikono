<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixture;

use App\Fixture\RosterArchive;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertNotEmpty($archive->programs);
    }

    #[Test]
    public function everyRosterSiteResolvesToASingleProgramOfferingItsType(): void
    {
        // AppStory gives each roster activity the listed program at its
        // project that offers its type, or the project's generated always-on
        // program when none does. `listedProgramFor()` throws when two match.
        $archive = self::archive();

        foreach ($archive->rosters as $roster) {
            foreach ($roster->sites as $site) {
                $type = $archive->projects[$site->projectKey]->activityType;
                self::assertNotNull($type, "Site \"{$site->projectKey}\" has no activity type.");

                $program = $archive->listedProgramFor($site->projectKey, $type);
                if (null !== $program) {
                    self::assertTrue(
                        (null === $program->startDate) && (null === $program->endDate),
                        "Program \"{$program->name}\" is dated; check it covers the shifted roster days.",
                    );
                }
            }
        }
    }

    #[Test]
    public function peggyLucasRunsComputerTuitionBesideItsSchoolSupport(): void
    {
        // The case programs exist for (ADR 0030, brainstorm 10): two programs
        // at one project, the roster's school support work untouched by it.
        $archive = self::archive();

        self::assertNotNull($archive->listedProgramFor('peggy_lucas', 'Computer tuition'));
        self::assertNull($archive->listedProgramFor('peggy_lucas', 'School support'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedPrograms(): iterable
    {
        yield 'unknown project' => [
            "  - {name: Tuition, project: nowhere, activity_types: [School support]}\n",
            'references unknown project "nowhere"',
        ];
        yield 'no activity type' => [
            "  - {name: Tuition, project: school, activity_types: []}\n",
            'offers no activity type',
        ];
        yield 'end before start' => [
            "  - {name: Tuition, project: school, activity_types: [School support], start: 2026-09-10, end: 2026-09-01}\n",
            'ends before it starts',
        ];
    }

    #[Test]
    #[DataProvider('malformedPrograms')]
    public function aMalformedProgramRefusesToLoad(string $programs, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        self::archiveWithPrograms($programs);
    }

    #[Test]
    public function twoProgramsOfferingARosterSitesTypeLeaveItsProgramAGuess(): void
    {
        $archive = self::archiveWithPrograms(
            "  - {name: Tuition, project: school, activity_types: [School support]}\n"
            . "  - {name: Mentoring, project: school, activity_types: [School support]}\n",
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Several programs at project "school" offer "School support"');

        $archive->listedProgramFor('school', 'School support');
    }

    #[Test]
    public function aRosterSiteAtAProjectWithNoActivityTypeRefusesToLoad(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('project "hub", which has no activity_type');

        self::archiveWithPrograms('', 'hub');
    }

    /**
     * A one-roster archive around the given `programs:` entries, written to
     * a temp file since `fromFile()` is the only way in.
     */
    private static function archiveWithPrograms(string $programs, string $rosterProject = 'school'): RosterArchive
    {
        $yaml = <<<YAML
            volunteers:
              - {name: Ada, active: true}
            escorts: []
            projects:
              - {key: school, name: School, branch: Nairobi (HQ), ownership: ucesco, activity_type: School support}
              - {key: hub, name: Hub, branch: Nairobi (HQ), ownership: ucesco}
            programs:

            YAML
            . ('' === $programs ? "  []\n" : $programs)
            . <<<YAML
            rosters:
              - date: 2026-09-01
                anchor: today
                sites:
                  - project: {$rosterProject}
                    volunteers: [{name: Ada}]

            YAML;

        $path = tempnam(sys_get_temp_dir(), 'roster');
        self::assertIsString($path);
        file_put_contents($path, $yaml);

        try {
            return RosterArchive::fromFile($path);
        } finally {
            unlink($path);
        }
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
    public function everyVolunteerWorksSitesAtASingleBranch(): void
    {
        // AppStory gives each volunteer one stay, at one branch, read off the
        // branches of the sites they worked (ADR 0026, ADR 0027). A volunteer
        // on both Nairobi and Mombasa rosters needs two stays the story can't
        // infer.
        $archive = self::archive();
        $branches = [];

        foreach ($archive->rosters as $roster) {
            foreach ($roster->sites as $site) {
                foreach ($site->volunteers as $slot) {
                    $branches[$slot->name][$archive->projects[$site->projectKey]->branch] = true;
                }
            }
        }

        foreach ($branches as $name => $seen) {
            self::assertCount(1, $seen, "Volunteer \"{$name}\" works sites at more than one branch.");
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
