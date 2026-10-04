<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ActivityType;
use App\Entity\BeneficiaryGroup;
use App\Entity\Program;
use App\Entity\Skill;
use App\Entity\Project;
use App\Repository\ActivityTypeRepository;
use App\Repository\BeneficiaryGroupRepository;
use App\Repository\SkillRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Program> */
final class ProgramFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => ''])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'group_by' => static fn(Project $project) => $project->getBranch()?->getName(),
                'choice_label' => static fn(Project $project) => $project->getName() . ($project->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn(ProjectRepository $projects): QueryBuilder => $projects->createOrderedByNameQueryBuilder(),
                'placeholder' => 'Choose a project',
            ])
            ->add('startDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Leave both dates empty for an always-on program.',
            ])
            ->add('endDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Leave empty for an open-ended program.',
            ])
            ->add('activityTypes', EntityType::class, [
                'class' => ActivityType::class,
                'choice_label' => 'name',
                'query_builder' => static fn(ActivityTypeRepository $activityTypes): QueryBuilder => $activityTypes->createOrderedByNameQueryBuilder(),
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
            ])
            ->add('skills', EntityType::class, [
                'class' => Skill::class,
                'choice_label' => 'name',
                'query_builder' => static fn(SkillRepository $skills): QueryBuilder => $skills->createOrderedByNameQueryBuilder(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'label' => 'Recommended Skills',
                // /matches links here with #skills: the row, so the label shows too.
                'row_attr' => ['id' => 'skills', 'class' => 'scroll-mt-4'],
                'help' => 'What the program needs. Its Matches page lists the volunteers who hold any of these.',
            ])
            ->add('suggestedRoles', TextareaType::class, ['required' => false])
            ->add('beneficiaryGroups', EntityType::class, [
                'class' => BeneficiaryGroup::class,
                'choice_label' => 'name',
                'query_builder' => static fn(BeneficiaryGroupRepository $groups): QueryBuilder => $groups->createOrderedByNameQueryBuilder(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => 'Who the program serves. Manage the list under Settings → Beneficiary groups.',
            ])
            ->add('beneficiariesReached', TextareaType::class, [
                'required' => false,
                'help' => 'How many it reaches, e.g. "120 pupils at Olympic School". Counts, never names.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Program::class,
            'attr' => ['class' => 'max-w-lg'],
        ]);
    }
}
