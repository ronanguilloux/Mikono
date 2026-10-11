<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Indicator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Indicator>
 */
final class IndicatorFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'attr' => ['placeholder' => 'Children reached'],
                'help' => 'What an achievement counts, so the number makes sense alone: "12 children reached".',
            ])
            ->add('description', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Indicator::class]);
    }
}
