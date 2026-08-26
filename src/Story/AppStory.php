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
        VolunteerFactory::new()->many(4)->create();

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
    }
}
