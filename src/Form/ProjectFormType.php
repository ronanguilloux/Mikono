<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Enum\ProjectLocation;
use App\Enum\ProjectOwnership;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => ''])
            ->add('location', EnumType::class, [
                'class' => ProjectLocation::class,
                'choice_label' => static fn(ProjectLocation $location) => $location->label(),
                'placeholder' => 'Choose a location',
            ])
            ->add('ownership', EnumType::class, [
                'class' => ProjectOwnership::class,
                'choice_label' => static fn(ProjectOwnership $ownership) => $ownership->label(),
                'placeholder' => 'Choose an ownership type',
            ])
            ->add('partnerOrganizationName', TextType::class, [
                'required' => false,
                'label' => 'Partner organization name',
                'help' => 'Required if this is a partner project.',
            ])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Active']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Project::class]);
    }
}
