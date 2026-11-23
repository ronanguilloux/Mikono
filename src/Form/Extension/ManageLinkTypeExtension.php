<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lets a field that picks from a Settings list point at the screen that
 * manages that list: `'manage_route' => 'indicator_index'`. The form theme
 * draws a "⚙ Manage" link on the label line, opening in a new tab, and the
 * manage-link Stimulus controller refreshes the field's options when the
 * reader comes back, so what they added there shows up without a reload.
 *
 * `manage_label` names the list in the link's tooltip and screen-reader
 * text ("Manage indicators"); it defaults to the field's label, lower-cased.
 */
final class ManageLinkTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['manage_route' => null, 'manage_label' => null])
            ->setAllowedTypes('manage_route', ['null', 'string'])
            ->setAllowedTypes('manage_label', ['null', 'string']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $route = $options['manage_route'];
        $label = $options['manage_label'];

        if (\is_string($route) && null === $label) {
            $fieldLabel = $view->vars['label'] ?? $view->vars['name'] ?? null;
            $label = \is_string($fieldLabel) ? mb_strtolower($fieldLabel) : null;
        }

        $view->vars['manage_route'] = $route;
        $view->vars['manage_label'] = $label;
    }
}
