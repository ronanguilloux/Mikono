<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
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

final class ActivityFormType extends AbstractType
{
    public function __construct(private readonly ProgramRepository $programs) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Backs /activities/{id}/edit only; new activities go through
        // BatchActivityFormType. Volunteers whose stays have all ended have
        // finished their stint, so they aren't offered — except the
        // activity's own volunteer, who stays selectable even once
        // deactivated, so fixing a typo on an old entry never forces
        // reassigning it to somebody else. Escorts get the same
        // hatch, and there it guards more than a typo fix: an escort missing
        // from the expanded choices would be silently dropped on save.
        $data = $options['data'] ?? null;
        $currentVolunteer = $data instanceof Activity ? $data->getVolunteer() : null;
        $currentEscorts = $data instanceof Activity ? array_values($data->getEscorts()->toArray()) : [];

        $builder
            ->add('date', DateType::class, ['widget' => 'single_text'])
            ->add('volunteer', EntityType::class, [
                'class' => Volunteer::class,
                // Only the escape-hatch volunteer can be inactive; asking the
                // rest would lazy-load every choice's stays for nothing.
                'choice_label' => static fn(Volunteer $volunteer) => $volunteer->getFullName() . ($volunteer === $currentVolunteer && !$volunteer->isActive() ? ' (inactive)' : ''),
                'query_builder' => static function (VolunteerRepository $volunteers) use ($currentVolunteer): QueryBuilder {
                    $builder = $volunteers->createWithCurrentOrUpcomingStayQueryBuilder();

                    if (null !== $currentVolunteer) {
                        $builder->orWhere('v = :current')->setParameter('current', $currentVolunteer);
                    }

                    return $builder;
                },
                'placeholder' => 'Choose a volunteer',
            ])
            ->add('program', EntityType::class, ActivityPickers::program())
            ->add('activityType', EntityType::class, ActivityPickers::activityType($this->programs->findProgramIdsByActivityType()))
            ->add('duration', EnumType::class, [
                'class' => ActivityDuration::class,
                'choice_label' => static fn(ActivityDuration $duration) => $duration->label(),
                'expanded' => true,
                'label' => 'Duration',
            ])
            ->add('durationOther', TextType::class, [
                'required' => false,
                'label' => 'If "Other", specify',
                'help' => 'Only used when "Other" is selected above — e.g. 1h, 2h, 2.5h.',
            ])
            // Multiple: the VM's rosters do say "Accompanied by Edna and
            // Sam". Her wording stays the label — see ADR 0013.
            ->add('escorts', EntityType::class, [
                'class' => Escort::class,
                'choice_label' => static fn(Escort $escort) => $escort->getName() . ($escort->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn(EscortRepository $escorts): QueryBuilder => $escorts->createActiveOrderedByNameQueryBuilder($currentEscorts),
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'required' => false,
                'label' => 'Accompanied by',
                'help' => 'Leave every box unticked if nobody accompanied the group.',
            ])
            ->add('notes', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Activity::class]);
    }
}
