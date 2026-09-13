<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Volunteer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class VolunteerFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['empty_data' => ''])
            // Optional — see ADR 0014. No 'empty_data' here, deliberately:
            // a blank submission must store null, not an empty string.
            ->add('lastName', TextType::class, ['required' => false])
            ->add('email', EmailType::class, ['required' => false])
            ->add('phone', TelType::class, ['required' => false])
            // No Active checkbox: active is read from the volunteer's stays (ADR 0026).
            ->add('notes', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Volunteer::class]);
    }
}
