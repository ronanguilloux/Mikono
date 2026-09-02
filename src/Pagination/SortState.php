<?php

declare(strict_types=1);

namespace App\Pagination;

/**
 * Which column a list view is sorted by, and which way — resolved once by
 * ListPaginator::sortState() and handed to the template as a single variable
 * so the seven views don't each pass three. See ADR 0011.
 *
 * Deliberately dumb: it knows column keys, never DQL paths. The controller's
 * sort map is the only place a key becomes a field, which is what keeps a
 * user-supplied string out of DQL.
 */
final readonly class SortState
{
    public const string ASC = 'asc';
    public const string DESC = 'desc';

    /**
     * @param list<string> $sortableKeys the column keys this view can sort by — the
     *                                   keys of the controller's sort map, so a column
     *                                   opts out of sorting by simply not being in it
     * @param ?string      $activeKey    null when there is no `?sort`, or when the one
     *                                   given isn't sortable here
     */
    public function __construct(
        public array $sortableKeys,
        public ?string $activeKey,
        public string $direction,
    ) {}

    public function isSortable(string $key): bool
    {
        return \in_array($key, $this->sortableKeys, true);
    }

    public function isActive(string $key): bool
    {
        return $key === $this->activeKey;
    }

    /**
     * Where a click on this column's header should go: ascending on the first
     * click, descending on the second. Every inactive column starts over at
     * ascending rather than inheriting the current direction — clicking a new
     * column shouldn't silently sort it backwards.
     */
    public function nextDirectionFor(string $key): string
    {
        return $this->isActive($key) && self::ASC === $this->direction
            ? self::DESC
            : self::ASC;
    }

    /** The `aria-sort` value for this column's `<th>`, or null if it isn't the sorted one. */
    public function ariaSortFor(string $key): ?string
    {
        if (!$this->isActive($key)) {
            return null;
        }

        return self::ASC === $this->direction ? 'ascending' : 'descending';
    }
}
