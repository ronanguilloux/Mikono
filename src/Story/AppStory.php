<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
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
                'isActive' => $volunteer->active,
            ]);
        }

        $escorts = [];
        foreach ($archive->escorts as $name) {
            $escorts[$name] = EscortFactory::createOne(['name' => $name]);
        }

        $activityTypes = [];
        $projects = [];
        foreach ($archive->projects as $key => $project) {
            $activityTypes[$project->activityType] ??= ActivityTypeFactory::createOne([
                'name' => $project->activityType,
            ]);
            $projects[$key] = ProjectFactory::createOne([
                'name' => $project->name,
                'location' => $project->location,
                'ownership' => $project->ownership,
                'partnerOrganizationName' => $project->partner,
                'isActive' => true,
            ]);
        }

        $today = new \DateTimeImmutable('today');

        foreach ($archive->rosters as $roster) {
            $date = $roster->dateRelativeTo($today);

            foreach ($roster->sites as $site) {
                $siteEscorts = array_map(
                    fn(string $name) => $escorts[$name] ?? throw new \RuntimeException(sprintf('Roster names an escort the archive does not list: "%s".', $name)),
                    $site->escorts,
                );

                foreach ($site->volunteers as $slot) {
                    ActivityFactory::createOne([
                        'date' => $date,
                        'volunteer' => $volunteers[$slot->name] ?? throw new \RuntimeException(sprintf('Roster names a volunteer the archive does not list: "%s".', $slot->name)),
                        'project' => $projects[$site->projectKey],
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
