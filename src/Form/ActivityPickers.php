<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ActivityType;
use App\Entity\Program;
use App\Repository\ActivityTypeRepository;
use App\Repository\ProgramRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * The program and activity-type pickers both activity forms share
 * (ADR 0030), so the single and batch forms can't drift apart.
 */
final class ActivityPickers
{
    /**
     * Native optgroups by branch: an activity's program must be at its
     * stay's branch (ADR 0027), so the picker shows where each one is.
     *
     * @return array<string, mixed> EntityType options
     */
    public static function program(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'class' => Program::class,
            'group_by' => static fn(Program $program) => $program->getProject()?->getBranch()?->getName(),
            'choice_label' => static function (Program $program) use ($today): string {
                $project = $program->getProject();
                $ended = null !== $program->getEndDate() && $program->getEndDate() < $today;

                return ($project?->getName() ?? '?') . ' — ' . $program->getName()
                    . (null !== $project && !$project->isActive() ? ' (inactive)' : '')
                    . ($ended ? ' (ended)' : '');
            },
            'query_builder' => static fn(ProgramRepository $programs): QueryBuilder => $programs->createOrderedQueryBuilder(),
            'placeholder' => 'Choose a program',
            'attr' => ['data-controller' => 'program-types', 'data-action' => 'program-types#filter'],
        ];
    }

    /**
     * Only types some program offers; each option names those programs for
     * the program-types Stimulus filter. The save still checks the chosen
     * program offers this one.
     *
     * @param array<int, list<int>> $programIdsByType from ProgramRepository::findProgramIdsByActivityType()
     *
     * @return array<string, mixed> EntityType options
     */
    public static function activityType(array $programIdsByType): array
    {
        return [
            'class' => ActivityType::class,
            'choice_label' => 'name',
            'choice_attr' => static fn(ActivityType $type) => [
                'data-programs' => implode(' ', $programIdsByType[(int) $type->getId()] ?? []),
            ],
            'query_builder' => static fn(ActivityTypeRepository $activityTypes): QueryBuilder => $activityTypes->createOfferedOrderedByNameQueryBuilder(),
            'placeholder' => 'Choose an activity type',
            'label' => 'Activity type',
        ];
    }
}
