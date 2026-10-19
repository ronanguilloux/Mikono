<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Program;
use App\Entity\Project;

/**
 * What one volunteer's activities add up to, over a whole record (the
 * volunteer page) or one stay (the stay page). Built by
 * VolunteerImpactCalculator; every figure but the two counts reads past
 * activities only, so planned work never inflates it — unless the caller
 * asks for planned work too (the stay page). Each activity counts as
 * one day, whatever its duration.
 *
 * @phpstan-type ImpactRow array{subject: Program|Project, count: int, days: int, engaged: int}
 * @phpstan-type Share array{label: string, count: int}
 */
final readonly class VolunteerImpact
{
    /**
     * @param array{programs: list<ImpactRow>, projects: list<ImpactRow>} $impact
     * @param list<Share>                                                 $byProgram      labelled "Project — Program"
     * @param list<Share>                                                 $byActivityType
     */
    public function __construct(
        public int $activityCount,
        /** Future-dated rows — logged, but not yet happened. */
        public int $plannedCount,
        /** Distinct dates, so two activities on one day count once. */
        public int $daysLogged,
        public array $impact,
        public array $byProgram,
        public array $byActivityType,
    ) {}
}
