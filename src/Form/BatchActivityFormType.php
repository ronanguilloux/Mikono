<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\BatchActivityInput;
use App\Entity\Escort;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use App\Repository\EscortRepository;
use App\Repository\ProgramRepository;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\QueryBuilder;
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
 * date/program/type/duration — see ActivityController::newBatch().
 */
final class BatchActivityFormType extends AbstractType
{
    public function __construct(private readonly ProgramRepository $programs) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Choose a date.')],
            ])
            ->add('program', EntityType::class, ActivityPickers::program() + [
                'constraints' => [new Assert\NotNull(message: 'Choose a program.')],
            ])
            ->add('activityType', EntityType::class, ActivityPickers::activityType($this->programs->findProgramIdsByActivityType()) + [
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
            ->add('escorts', EntityType::class, [
                'class' => Escort::class,
                'choice_label' => 'name',
                'query_builder' => static fn(EscortRepository $escorts): QueryBuilder => $escorts->createOrderedByNameQueryBuilder(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Accompanied by',
                'help' => 'The staff accompanying this session — applied to every volunteer logged below. Leave empty if nobody did.',
            ])
            ->add('volunteers', EntityType::class, [
                'class' => Volunteer::class,
                'choice_label' => static fn(Volunteer $volunteer) => $volunteer->getFullName(),
                'choice_attr' => static fn(Volunteer $volunteer) => [
                    'data-name' => $volunteer->getFullName(),
                ],
                // Current or upcoming stays only — this form only ever creates
                // new activities, so somebody who has finished their stint is
                // never a valid answer to "who attended?".
                'query_builder' => static fn(VolunteerRepository $volunteers): QueryBuilder => $volunteers->createWithCurrentOrUpcomingStayQueryBuilder(),
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
                new Assert\Callback(static fn(BatchActivityInput $data, ExecutionContextInterface $context) => ActivityDuration::checkOtherIsSpecified($data->duration, $data->durationOther, $context)),
            ],
        ]);
    }
}
