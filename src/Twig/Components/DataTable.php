<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Generic list table reused by every simple CRUD screen (Volunteers,
 * Projects, Activity Types, Users). The caller shapes rows/actions —
 * this component only renders them.
 *
 * @phpstan-type Action array{label: string, url: string, method?: string, confirm?: string, csrfToken?: string}
 * @phpstan-type Row array{cells: array<string, string>, actions: list<Action>}
 */
#[AsTwigComponent]
final class DataTable
{
    /** @var list<array{key: string, label: string}> */
    public array $columns = [];

    /** @var list<Row> */
    public array $rows = [];

    public string $emptyMessage = 'Nothing here yet.';
}
