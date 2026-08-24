<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\Project;
use App\Entity\Volunteer;
use App\Enum\ActivityDuration;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ActivityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, ['widget' => 'single_text'])
            ->add('volunteer', EntityType::class, [
                'class' => Volunteer::class,
                'choice_label' => static fn (Volunteer $volunteer) => $volunteer->getFullName().($volunteer->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn ($repo) => $repo->createQueryBuilder('v')->orderBy('v.lastName', 'ASC')->addOrderBy('v.firstName', 'ASC'),
                'placeholder' => 'Choose a volunteer',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn (Project $project) => $project->getName().($project->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn ($repo) => $repo->createQueryBuilder('p')->orderBy('p.name', 'ASC'),
                'placeholder' => 'Choose a project',
            ])
            ->add('activityType', EntityType::class, [
                'class' => ActivityType::class,
                'choice_label' => 'name',
                'query_builder' => static fn ($repo) => $repo->createQueryBuilder('t')->orderBy('t.name', 'ASC'),
                'placeholder' => 'Choose an activity type',
                'label' => 'Activity type',
            ])
            ->add('duration', EnumType::class, [
                'class' => ActivityDuration::class,
                'choice_label' => static fn (ActivityDuration $duration) => $duration->label(),
                'expanded' => true,
                'label' => 'Duration',
            ])
            ->add('notes', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Activity::class]);
    }
}
