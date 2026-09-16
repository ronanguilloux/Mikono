<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\EscortFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\StayFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use App\Fixture\RosterArchive;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * The dev and demo dataset — every row of it real.
 *
 * Nothing here is generated: the volunteers, escorts, sites, dates and roster
 * notes all come from `docs/fixtures/rosters.yaml`, transcribed from a month
 * of the VM's own WhatsApp roster messages. See ADR 0012 for why, and
 * `docs/fixtures/README.md` for what the archive can and cannot supply.
 *
 * To grow the dataset, add to the archive — not to this file.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function build(): void
    {
        $archive = RosterArchive::fromFile($this->projectDir . '/' . RosterArchive::DEFAULT_PATH);

        // The one account the app has, and the one the archive's activities
        // are all logged by — there is no second user to attribute them to.
        $admin = UserFactory::new()->admin()->create([
            'email' => 'ronan.guilloux@gmail.com',
            'fullName' => 'Ronan Guilloux',
        ]);

        $volunteers = [];
        foreach ($archive->volunteers as $volunteer) {
            // First name only, no contact details: that is all the rosters
            // carry, and the fixtures don't fill the gaps in.
            $volunteers[$volunteer->name] = VolunteerFactory::createOne([
                'firstName' => $volunteer->name,
                'lastName' => null,
                'email' => null,
                'phone' => null,
                'notes' => $volunteer->notes,
                // Built below from the rosters, not the factory's default.
                'stays' => [],
            ]);
        }

        $escorts = [];
        foreach ($archive->escorts as $name) {
            $escorts[$name] = EscortFactory::createOne(['name' => $name]);
        }

        $activityTypes = [];
        $projects = [];
        $programs = [];
        foreach ($archive->projects as $key => $project) {
            $activityTypes[$project->activityType] ??= ActivityTypeFactory::createOne([
                'name' => $project->activityType,
            ]);
            $projects[$key] = ProjectFactory::createOne([
                'name' => $project->name,
                'branch' => BranchFactory::find(['name' => $project->branch]),
                'ownership' => $project->ownership,
                'partnerOrganizationName' => $project->partner,
                'isActive' => true,
            ]);
            // The archive knows one type per project and no programs, so each
            // project gets one always-on program named after its type
            // (ADR 0030) — not an invented one (ADR 0012).
            $programs[$key] = ProgramFactory::createOne([
                'name' => $project->activityType,
                'project' => $projects[$key],
                'activityTypes' => [$activityTypes[$project->activityType]],
            ]);
        }

        // The whole archive slides onto the day the fixtures load: the calendar
        // moves, the people don't, and the days keep their real spacing.
        $shift = $archive->anchorDay()->diff(new \DateTimeImmutable('today'));

        // One stay per volunteer, read off the archive rather than invented
        // (ADR 0012, ADR 0026): first to last roster appearance, at the branch
        // of the sites they worked. `active: true` means still on the rosters
        // as the archive ends (docs/fixtures/README.md rule 11), so that stay
        // runs to the archive's last day.
        $spans = [];
        $archiveEnd = null;
        foreach ($archive->rosters as $roster) {
            $date = $roster->date->add($shift);
            $archiveEnd = max($archiveEnd ?? $date, $date);

            foreach ($roster->sites as $site) {
                $branch = $projects[$site->projectKey]->getBranch();

                foreach ($site->volunteers as $slot) {
                    $span = $spans[$slot->name] ?? ['start' => $date, 'end' => $date, 'branch' => $branch];
                    if ($span['branch'] !== $branch) {
                        throw new \RuntimeException(sprintf('"%s" works sites at two branches; the fixtures give each volunteer a single stay.', $slot->name));
                    }

                    $spans[$slot->name] = ['start' => min($span['start'], $date), 'end' => max($span['end'], $date), 'branch' => $branch];
                }
            }
        }

        $stays = [];
        foreach ($archive->volunteers as $volunteer) {
            $span = $spans[$volunteer->name] ?? null;
            if (null === $span || null === $archiveEnd) {
                continue;
            }

            $stays[$volunteer->name] = StayFactory::createOne([
                'volunteer' => $volunteers[$volunteer->name],
                'branch' => $span['branch'],
                'startDate' => $span['start'],
                'endDate' => $volunteer->active ? max($span['end'], $archiveEnd) : $span['end'],
            ]);
        }

        foreach ($archive->rosters as $roster) {
            $date = $roster->date->add($shift);

            foreach ($roster->sites as $site) {
                $siteEscorts = array_map(
                    fn(string $name) => $escorts[$name] ?? throw new \RuntimeException(sprintf('Roster names an escort the archive does not list: "%s".', $name)),
                    $site->escorts,
                );

                foreach ($site->volunteers as $slot) {
                    ActivityFactory::createOne([
                        'date' => $date,
                        'volunteer' => $volunteers[$slot->name] ?? throw new \RuntimeException(sprintf('Roster names a volunteer the archive does not list: "%s".', $slot->name)),
                        'stay' => $stays[$slot->name],
                        'program' => $programs[$site->projectKey],
                        'activityType' => $activityTypes[$archive->projects[$site->projectKey]->activityType],
                        'duration' => $site->duration,
                        'notes' => $slot->note,
                        'escorts' => $siteEscorts,
                        'loggedBy' => $admin,
                    ]);
                }
            }
        }
    }
}
