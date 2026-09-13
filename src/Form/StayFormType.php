<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Branch;
use App\Entity\Stay;
use App\Repository\BranchRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Stay>
 */
final class StayFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Every branch, inactive ones labelled rather than hidden: editing
            // an old stay must never force moving it to another branch.
            ->add('branch', EntityType::class, [
                'class' => Branch::class,
                'choice_label' => static fn(Branch $branch) => $branch->getName() . ($branch->isActive() ? '' : ' (inactive)'),
                'query_builder' => static fn(BranchRepository $branches): QueryBuilder => $branches->createOrderedByNameQueryBuilder(),
                'placeholder' => 'Choose a branch',
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'First day',
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Last day',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Stay::class]);
    }
}
