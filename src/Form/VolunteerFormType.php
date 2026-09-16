<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Volunteer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

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
            // The profile fields are all optional, with no 'empty_data', for the
            // same reason as lastName (ADR 0032).
            ->add('nationality', CountryType::class, ['required' => false, 'placeholder' => '—'])
            ->add('countryOfResidence', CountryType::class, ['required' => false, 'placeholder' => '—'])
            ->add('dateOfBirth', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('profession', TextType::class, ['required' => false])
            ->add('skills', TextareaType::class, ['required' => false])
            ->add('interests', TextareaType::class, ['required' => false])
            ->add('emergencyContacts', TextareaType::class, [
                'required' => false,
                'help' => 'Name, relationship and phone number, one contact per line.',
            ])
            // Unmapped: the controller re-encodes the upload before it becomes a
            // VolunteerPhoto (ADR 0032). Listing the types in `accept` makes iOS
            // convert HEIC to JPEG on upload.
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Image(
                        maxSize: '10M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Upload a JPEG, PNG or WebP picture.',
                        // Decoding costs ~4 bytes a pixel; this keeps one upload
                        // well inside PHP's memory limit on a small VPS.
                        maxPixels: 25_000_000,
                    ),
                ],
            ]);

        $volunteer = $builder->getData();
        if ($volunteer instanceof Volunteer && null !== $volunteer->getPhoto()) {
            $builder->add('removePhoto', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Remove the current photo',
            ]);
        }

        // No Active checkbox: active is read from the volunteer's stays (ADR 0026).
        $builder->add('notes', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Volunteer::class]);
    }
}
