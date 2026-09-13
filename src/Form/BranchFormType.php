<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Branch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Branch>
 */
final class BranchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => ''])
            ->add('physicalLocation', TextType::class, ['empty_data' => '', 'label' => 'Physical location'])
            ->add('projectZones', TextareaType::class, ['required' => false, 'label' => 'Project zones'])
            ->add('programFocus', TextareaType::class, ['required' => false, 'label' => 'Core program focus'])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Active']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Branch::class,
            'attr' => ['class' => 'max-w-lg'],
        ]);
    }
}
