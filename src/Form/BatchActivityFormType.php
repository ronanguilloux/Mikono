<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\BatchActivityInput;
use App\Entity\ActivityType;
use App\Entity\Escort;
use App\Entity\Project;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Backed by BatchActivityInput, not an entity: one submission fans out
 * into one Activity per selected volunteer, all sharing the same
 * date/project/type/duration — see ActivityController::newBatch().
 */
final class BatchActivityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Choose a date.')],
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn(Project $project) => $project->getName() . ($project->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn($repo) => $repo->createQueryBuilder('p')->orderBy('p.name', 'ASC'),
                'placeholder' => 'Choose a project',
                'constraints' => [new Assert\NotNull(message: 'Choose a project.')],
            ])
            ->add('activityType', EntityType::class, [
                'class' => ActivityType::class,
                'choice_label' => 'name',
                'query_builder' => static fn($repo) => $repo->createQueryBuilder('t')->orderBy('t.name', 'ASC'),
                'placeholder' => 'Choose an activity type',
                'label' => 'Activity type',
                'constraints' => [new Assert\NotNull(message: 'Choose an activity type.')],
            ])
            ->add('duration', EnumType::class, [
                'class' => ActivityDuration::class,
                'choice_label' => static fn(ActivityDuration $duration) => $duration->label(),
                'choice_attr' => static fn(ActivityDuration $duration) => array_filter([
                    'data-action' => 'change->batch-activity-form#toggleOtherField',
                    'data-batch-activity-form-target' => ActivityDuration::Other === $duration ? 'otherRadio' : null,
                ]),
                'expanded' => true,
                'label' => 'Duration',
                'constraints' => [new Assert\NotNull(message: 'Choose a duration.')],
            ])
            ->add('durationOther', TextType::class, [
                'required' => false,
                'label' => 'If "Other", specify',
                'help' => 'Only used when "Other" is selected above — e.g. 1h, 2h, 2.5h.',
            ])
            ->add('escort', EntityType::class, [
                'class' => Escort::class,
                'choice_label' => 'name',
                'query_builder' => static fn($repo) => $repo->createQueryBuilder('e')->orderBy('e.name', 'ASC'),
                'placeholder' => '— No escort recorded —',
                'required' => false,
                'label' => 'Accompanied by',
                'help' => 'One staff escort per session — applied to every volunteer logged below.',
            ])
            ->add('volunteers', EntityType::class, [
                'class' => Volunteer::class,
                'choice_label' => static fn(Volunteer $volunteer) => $volunteer->getFullName(),
                'choice_attr' => static fn(Volunteer $volunteer) => [
                    'data-name' => $volunteer->getFullName(),
                    'data-inactive' => $volunteer->isActive() ? '0' : '1',
                ],
                'query_builder' => static fn($repo) => $repo->createQueryBuilder('v')->orderBy('v.lastName', 'ASC')->addOrderBy('v.firstName', 'ASC'),
                'multiple' => true,
                'expanded' => true,
                'label' => 'Who attended?',
                'constraints' => [new Assert\Count(min: 1, minMessage: 'Select at least one volunteer.')],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'help' => 'Applied to every activity entry this creates.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BatchActivityInput::class,
            'constraints' => [
                new Assert\Callback(static function (BatchActivityInput $data, ExecutionContextInterface $context): void {
                    if (ActivityDuration::Other === $data->duration && (null === $data->durationOther || '' === trim($data->durationOther))) {
                        $context->buildViolation('Please specify the duration when choosing "Other".')
                            ->atPath('durationOther')
                            ->addViolation();
                    }
                }),
            ],
        ]);
    }
}
