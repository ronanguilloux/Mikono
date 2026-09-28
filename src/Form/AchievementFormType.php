<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Achievement;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Achievement>
 */
final class AchievementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'empty_data' => '',
                'attr' => ['placeholder' => 'Building a library'],
            ])
            ->add('achievedOn', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Achieved on',
                'help' => 'A day within the stay — the anniversary counts from it.',
            ])
            // Every project, labelled with its branch; AchievementController
            // refuses one at another branch than the stay's (ADR 0027).
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn(Project $project) => sprintf('%s (%s)', $project->getName(), $project->getBranch()?->getName() ?? '—'),
                'query_builder' => static fn(ProjectRepository $projects): QueryBuilder => $projects->createOrderedByNameQueryBuilder(),
                'placeholder' => 'Choose a project',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'help' => 'Optional. Don\'t name children or donors.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Achievement::class]);
    }
}
