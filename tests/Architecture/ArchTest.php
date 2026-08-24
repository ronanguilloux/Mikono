<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Encodes ADR 0004's one architecture rule: entities stay framework-agnostic
 * of controllers and Twig components. Informational, not a merge gate, per
 * that ADR.
 */
final class ArchTest
{
    #[TestRule]
    public function entities_do_not_depend_on_controllers_or_twig(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Entity'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('App\Controller'),
                Selector::inNamespace('App\Twig'),
            )
            ->because('entities must stay framework-agnostic of controllers and Twig components (ADR 0004).');
    }
}
