<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class)
            ->add('email', EmailType::class)
            ->add('roles', ChoiceType::class, [
                'choices' => ['Volunteer Manager (standard access)' => 'ROLE_USER', 'Admin (can also manage user accounts)' => 'ROLE_ADMIN'],
                'multiple' => false,
                'expanded' => false,
                'getter' => static fn (User $user): string => $user->isAdmin() ? 'ROLE_ADMIN' : 'ROLE_USER',
                'setter' => static fn (User $user, string $role) => $user->setRoles([$role]),
            ])
            ->add('isActive', CheckboxType::class, ['required' => false, 'label' => 'Active'])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['password_required'],
                'help' => $options['password_required'] ? null : 'Leave blank to keep the current password.',
                'constraints' => $options['password_required']
                    ? [new NotBlank(), new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')]
                    : [new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'password_required' => true]);
        $resolver->setAllowedTypes('password_required', 'bool');
    }
}
