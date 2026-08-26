<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\ActivityDuration;
use App\Enum\ProjectLocation;
use App\Enum\ProjectOwnership;
use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        $admin = UserFactory::new()->admin()->create([
            'email' => 'ronan.guilloux@gmail.com',
            'fullName' => 'Ronan Guilloux',
        ]);

        $brightAchievers = ProjectFactory::new()->partner()->create([
            'name' => 'Bright Achievers',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Bright Achievers High School',
        ]);
        $mombasaProject = ProjectFactory::new()->create([
            'name' => 'UCESCO Mombasa Youth Centre',
            'location' => ProjectLocation::Mombasa,
            'ownership' => ProjectOwnership::Ucesco,
        ]);

        $computerLessons = ActivityTypeFactory::createOne(['name' => 'Computer lessons']);
        $tutoring = ActivityTypeFactory::createOne(['name' => 'Tutoring']);

        $ronanVolunteer = VolunteerFactory::createOne(['firstName' => 'Ronan', 'lastName' => 'Guilloux']);
        $extraVolunteers = VolunteerFactory::new()->many(4)->create();

        // The literal worked example this app's v0.1 scope is defined by.
        ActivityFactory::createOne([
            'volunteer' => $ronanVolunteer,
            'project' => $brightAchievers,
            'activityType' => $computerLessons,
            'date' => new \DateTimeImmutable('2026-08-11'),
            'duration' => ActivityDuration::FullDay,
            'notes' => 'Delivered Computer lessons to students',
            'loggedBy' => $admin,
        ]);
        ActivityFactory::new()->many(6)->create([
            'project' => $mombasaProject,
            'activityType' => $tutoring,
            'loggedBy' => $admin,
        ]);

        // Real UCESCO project/activity-type breadth, observed in the VM's
        // actual nightly WhatsApp roster messages (see docs/brainstorm/04).
        $ucescoHq = ProjectFactory::new()->create([
            'name' => 'UCESCO HQ',
            'location' => ProjectLocation::Kibera,
            'ownership' => ProjectOwnership::Ucesco,
        ]);
        $beyondZero = ProjectFactory::new()->partner()->create([
            'name' => 'Beyond Zero clinic',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Beyond Zero',
        ]);
        $ackClinic = ProjectFactory::new()->partner()->create([
            'name' => 'ACK clinic',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Anglican Church of Kenya (ACK)',
        ]);
        $peggyLucas = ProjectFactory::new()->create([
            'name' => 'Peggy Lucas school',
            'location' => ProjectLocation::Kibera,
            'ownership' => ProjectOwnership::Ucesco,
        ]);
        $mveti = ProjectFactory::new()->partner()->create([
            'name' => 'MVETI',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'MVETI',
        ]);
        $toiSchoolField = ProjectFactory::new()->partner()->create([
            'name' => 'Toi School Field',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Toi School',
        ]);
        $dreamsOfHope = ProjectFactory::new()->partner()->create([
            'name' => 'Dreams of Hope Kids',
            'location' => ProjectLocation::Kibera,
            'partnerOrganizationName' => 'Dreams of Hope',
        ]);
        $mintoOrphanage = ProjectFactory::new()->partner()->create([
            'name' => "Minto Children's Orphanage",
            'location' => ProjectLocation::Mombasa,
            'partnerOrganizationName' => "Minto Children's Orphanage",
        ]);
        $mtwapaBeach = ProjectFactory::new()->create([
            'name' => 'Mtwapa Beach',
            'location' => ProjectLocation::Mombasa,
            'ownership' => ProjectOwnership::Ucesco,
        ]);
        $nyaliBeach = ProjectFactory::new()->create([
            'name' => 'Nyali Beach',
            'location' => ProjectLocation::Mombasa,
            'ownership' => ProjectOwnership::Ucesco,
        ]);
        $mombasaOffice = ProjectFactory::new()->create([
            'name' => 'Mombasa Office',
            'location' => ProjectLocation::Mombasa,
            'ownership' => ProjectOwnership::Ucesco,
        ]);
        $mombasaHomeVisits = ProjectFactory::new()->create([
            'name' => 'Mombasa Home Visits',
            'location' => ProjectLocation::Mombasa,
            'ownership' => ProjectOwnership::Ucesco,
        ]);

        $orientation = ActivityTypeFactory::createOne(['name' => 'Orientation']);
        $clinicSupport = ActivityTypeFactory::createOne(['name' => 'Clinic support']);
        $schoolSupport = ActivityTypeFactory::createOne(['name' => 'School support']);
        $vocationalTrainingSupport = ActivityTypeFactory::createOne(['name' => 'Vocational training support']);
        $sports = ActivityTypeFactory::createOne(['name' => 'Sports']);
        $homeVisit = ActivityTypeFactory::createOne(['name' => 'Home visit']);
        $orphanageSupport = ActivityTypeFactory::createOne(['name' => 'Orphanage support']);
        $beachCleanUp = ActivityTypeFactory::createOne(['name' => 'Beach clean-up']);
        $officeSupport = ActivityTypeFactory::createOne(['name' => 'Office/admin support']);

        // Orientation is every new volunteer's first activity in real life:
        // a slides presentation at HQ, then a tour of sites.
        ActivityFactory::createOne([
            'volunteer' => $extraVolunteers[0],
            'project' => $ucescoHq,
            'activityType' => $orientation,
            'date' => new \DateTimeImmutable('2026-08-01'),
            'duration' => ActivityDuration::HalfDay,
            'notes' => 'Orientation: slides presentation at HQ, then a tour of sites.',
            'loggedBy' => $admin,
        ]);

        foreach ([
            [$beyondZero, $clinicSupport],
            [$ackClinic, $clinicSupport],
            [$peggyLucas, $schoolSupport],
            [$mveti, $vocationalTrainingSupport],
            [$toiSchoolField, $sports],
            [$dreamsOfHope, $orphanageSupport],
            [$mintoOrphanage, $orphanageSupport],
            [$mtwapaBeach, $beachCleanUp],
            [$nyaliBeach, $beachCleanUp],
            [$mombasaOffice, $officeSupport],
            [$mombasaHomeVisits, $homeVisit],
        ] as [$project, $activityType]) {
            ActivityFactory::new()->many(2)->create([
                'project' => $project,
                'activityType' => $activityType,
                'loggedBy' => $admin,
            ]);
        }
    }
}
