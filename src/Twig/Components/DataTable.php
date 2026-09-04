<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Pagination\SortState;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPaginationInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Generic list table reused by every simple CRUD screen (Volunteers,
 * Projects, Activity Types, Escorts, Users) and by the Reports breakdowns.
 * The caller shapes rows/actions — this component only renders them.
 *
 * An action carrying `disabledReason` is rendered inert instead of linked —
 * that is how the Volunteers and Projects indexes show Delete as unavailable
 * on rows the delete-guard would block, rather than letting the reader find
 * out from a flash after confirming. Such an action needs no `url`, which is
 * why that key is optional.
 *
 * @phpstan-type Action array{label: string, url?: string, method?: string, confirm?: string, csrfToken?: string, disabledReason?: string}
 * @phpstan-type Row array{cells: array<string, string>, badges?: array<string, string>, actions?: list<Action>}
 */
#[AsTwigComponent]
final class DataTable
{
    /** @var list<array{key: string, label: string}> */
    public array $columns = [];

    /**
     * A row's optional `badges` key is a column key => badge label map, drawn
     * as a pill after that cell's text — the Reports breakdowns tag a
     * future-dated "Most recent" with `Planned`. Keyed by column rather than
     * per row so a badge lands on the cell it qualifies, and left out of the
     * cell string itself so sorting, `number_format()` and the print panel all
     * still see the plain value.
     *
     * @var list<Row>
     */
    public array $rows = [];

    public string $emptyMessage = 'Nothing here yet.';

    /**
     * The trailing actions column is skipped for read-only tables — the
     * Reports breakdowns have no per-row actions and would otherwise get a
     * phantom empty column.
     *
     * Explicit rather than inferred from $rows: inferring would silently drop
     * the column (and shift the empty-state colspan) on a CRUD list that
     * simply happens to have no rows yet.
     */
    public bool $withActions = true;

    /**
     * Rendered as a PaginationBar below the table when set. Left null by the
     * Activities index, which renders that bar itself so it stays reachable
     * from the mobile card layout — see templates/activity/index.html.twig.
     *
     * @var SlidingPaginationInterface<int, mixed>|null
     */
    public ?SlidingPaginationInterface $pagination = null;

    /**
     * Turns the headers into sort links. Null leaves every header as plain
     * text — which is what the Reports print panel wants, since it renders
     * both breakdowns complete and there is nothing to re-order.
     *
     * A column becomes clickable iff its key is in the controller's sort map,
     * carried here as SortState::$sortableKeys. Sortability deliberately isn't
     * a flag on the column definition too: one source of truth means Activity
     * Type's Description, Activity's Duration and User's Role opt out by
     * simply not being in the map, with no special-casing here. See ADR 0011.
     */
    public ?SortState $sortState = null;
}
