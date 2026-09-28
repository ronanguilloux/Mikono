<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\ActivityDuration;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\BranchFactory;
use App\Factory\EscortFactory;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\SkillFactory;
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
 * Nothing here is generated. The reference data comes from
 * `docs/fixtures/rosters.yaml`: escorts, sites and activity types from a month
 * of the VM's own WhatsApp roster messages, and the programs from UCESCO's
 * public Volunteer World listing. See ADR 0012 for why, and
 * `docs/fixtures/README.md` for what each source can and cannot supply.
 *
 * The archive's volunteers and rosters stay in the YAML. The only people data
 * seeded is one fake volunteer with one stay and one activity, at the end of
 * `build()`.
 *
 * To grow the reference data, add to the archive — not to this file.
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

        // The one account the app has.
        $admin = UserFactory::new()->admin()->create([
            'email' => 'ronan.guilloux@gmail.com',
            'fullName' => 'Ronan Guilloux',
        ]);

        foreach ($archive->escorts as $name) {
            EscortFactory::createOne(['name' => $name]);
        }

        $typeNames = array_merge(
            array_map(static fn($project) => $project->activityType, array_values($archive->projects)),
            ...array_map(static fn($program) => $program->activityTypes, $archive->programs),
        );
        $activityTypes = [];
        foreach (array_unique(array_filter($typeNames, static fn(?string $name) => null !== $name)) as $name) {
            $activityTypes[$name] = ActivityTypeFactory::createOne(['name' => $name]);
        }

        $projects = [];
        foreach ($archive->projects as $key => $project) {
            $projects[$key] = ProjectFactory::createOne([
                'name' => $project->name,
                'branch' => BranchFactory::find(['name' => $project->branch]),
                'ownership' => $project->ownership,
                'partnerOrganizationName' => $project->partner,
                'isActive' => true,
            ]);
        }

        // Listed dates are real calendar dates. None are dated yet.
        foreach ($archive->programs as $program) {
            ProgramFactory::createOne([
                'name' => $program->name,
                'project' => $projects[$program->projectKey],
                'activityTypes' => array_map(static fn(string $name) => $activityTypes[$name], $program->activityTypes),
                'suggestedRoles' => $program->suggestedRoles,
                'startDate' => $program->startDate,
                'endDate' => $program->endDate,
            ]);
        }

        // A roster site's type with no listed program offering it at that
        // project gets one always-on program named after the type (ADR 0030),
        // not an invented one (ADR 0012) — so the site can still be logged.
        foreach ($archive->projects as $key => $project) {
            if (null === $project->activityType || null !== $archive->listedProgramFor($key, $project->activityType)) {
                continue;
            }

            ProgramFactory::createOne([
                'name' => $project->activityType,
                'project' => $projects[$key],
                'activityTypes' => [$activityTypes[$project->activityType]],
            ]);
        }

        // One fake volunteer, entered by hand in the dev app on 2026-09-28 and
        // copied here, its dates made relative to the load day — every value
        // is made up. No passport number
        // (ADR 0033) and no photo.
        $ronan = VolunteerFactory::createOne([
            'firstName' => 'Ronan',
            'lastName' => 'Guilloux',
            'email' => 'ronan.guilloux@yahoo.com',
            'phone' => '+33612345678',
            'notes' => 'Some.',
            'nationality' => 'FR',
            'countryOfResidence' => 'FR',
            // Twenty today, so the birthday lands on the load day.
            'dateOfBirth' => new \DateTimeImmutable('today -20 years'),
            'profession' => 'Ice Cream Flavor Guru',
            'skills' => [SkillFactory::find(['name' => 'Computer & digital skills'])],
            'interests' => 'Some.',
            'emergencyContacts' => 'Some.',
            'accommodationPreference' => 'None.',
            'pickupAirport' => '3 Sept 1pm, Nairobi',
            'socialMediaUrl' => 'https://github.com/ronanguilloux',
            'supervisor' => 'Edna',
            'passportExpiresOn' => new \DateTimeImmutable('2030-01-01'),
            'stays' => [],
        ]);

        $stay = StayFactory::createOne([
            'volunteer' => $ronan,
            'branch' => BranchFactory::find(['name' => 'Nairobi (HQ)']),
            // The load day's month, so he is active whenever fixtures load.
            'startDate' => new \DateTimeImmutable('first day of this month midnight'),
            'endDate' => new \DateTimeImmutable('last day of this month midnight'),
        ]);

        ActivityFactory::createOne([
            'date' => new \DateTimeImmutable('today'),
            'volunteer' => $ronan,
            'stay' => $stay,
            'program' => ProgramFactory::find(['name' => 'Computer Tuition']),
            'activityType' => $activityTypes['Computer tuition'],
            'duration' => ActivityDuration::HalfDay,
            'escorts' => [EscortFactory::find(['name' => 'Edna'])],
            'loggedBy' => $admin,
        ]);
    }
}
