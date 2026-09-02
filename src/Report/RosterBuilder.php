<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\ActivityRepository;

/**
 * Turns a day's flat Activity rows into the project-grouped shape the VM's own
 * roster messages use. This is the first read path for Activity::$escorts —
 * the field exists precisely so the "Accompanied by ..." line can come back
 * out here instead of being retyped by hand every evening, which is also why
 * it renders every escort on the group rather than just one (ADR 0013).
 */
final class RosterBuilder
{
    public function __construct(private readonly ActivityRepository $activities) {}

    public function buildFor(\DateTimeImmutable $date): Roster
    {
        /** @var array<string, array{projectName: string, activityTypeName: string, slots: list<RosterSlot>, escortNames: list<string>}> $groups */
        $groups = [];
        /** @var array<string, string> $firstGroupPerVolunteer */
        $firstGroupPerVolunteer = [];

        foreach ($this->activities->findByDate($date) as $activity) {
            $projectName = $activity->getProject()?->getName() ?? 'Unknown project';
            $activityTypeName = $activity->getActivityType()?->getName() ?? 'Unknown activity';
            $key = $projectName . "\0" . $activityTypeName;

            $groups[$key] ??= [
                'projectName' => $projectName,
                'activityTypeName' => $activityTypeName,
                'slots' => [],
                'escortNames' => [],
            ];

            $volunteerName = $activity->getVolunteer()?->getFullName() ?? 'Unknown volunteer';
            $firstGroupPerVolunteer[$volunteerName] ??= $key;
            $groups[$key]['slots'][] = new RosterSlot($volunteerName, $firstGroupPerVolunteer[$volunteerName] !== $key);

            foreach ($activity->getEscortNames() as $escortName) {
                if (!in_array($escortName, $groups[$key]['escortNames'], true)) {
                    $groups[$key]['escortNames'][] = $escortName;
                }
            }
        }

        return new Roster($date, array_values(array_map(
            static fn(array $group) => new RosterGroup(
                $group['projectName'],
                $group['activityTypeName'],
                $group['slots'],
                $group['escortNames'],
            ),
            $groups,
        )));
    }
}
