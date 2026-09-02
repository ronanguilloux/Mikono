<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The one place `page`, `perPage`, `sort` and `direction` are read off the
 * query string, so the seven list views don't each carry their own whitelist.
 * See ADR 0009 (pagination) and ADR 0011 (sorting).
 *
 * Bad input never 404s. An unknown `perPage` falls back to the default, a
 * `page` below 1 clamps to 1, a `page` past the end serves the last page
 * (`page_out_of_range: fix` in config/packages/knp_paginator.yaml), and an
 * unknown `sort` leaves the view's own order alone. There is one
 * non-technical user working from bookmarked URLs — landing on the last page
 * beats a dead end, and none of these params are worth an error screen.
 */
final class ListPaginator
{
    public const int DEFAULT_PER_PAGE = 25;

    /** @var list<int> */
    public const array PER_PAGE_OPTIONS = [25, 50, 100];

    /**
     * "All" is a finite ceiling rather than 0 or PHP_INT_MAX: Knp divides by
     * the limit to get the page count, so 0 is a division by zero, and a
     * bigint LIMIT is dialect-fragile. At ~20 activities a week this ceiling
     * is roughly a century out.
     */
    public const int ALL_PER_PAGE = 100_000;

    /**
     * Knp's own sorting is switched off, not merely left unused. Its default
     * `sortFieldParameterName` is "sort" — the very param this class owns — so
     * without this the bundle would pick our column key up and push it into an
     * ORDER BY tree walker as a DQL path: `?sort=activityType` becomes
     * `a.activityType`, an association, i.e. a 500. Both Sortable subscribers
     * (Doctrine\ORM\QuerySubscriber and ArraySubscriber) short-circuit on a
     * null field name, and Paginator::paginate() merges options with a plain
     * array_merge and no resolver, so null passes through cleanly.
     *
     * Null rather than an unused-looking parameter name: a name is still
     * forgeable by hand in the URL bar, and this app's sort resolution has to
     * be the only path to an ORDER BY. See ADR 0011.
     */
    private const array SORTING_OFF = [PaginatorInterface::SORT_FIELD_PARAMETER_NAME => null];

    public function __construct(private readonly PaginatorInterface $paginator) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass the type $queryBuilder selects — unused at
     *                                     runtime, it exists so callers iterating the
     *                                     result get the entity type instead of mixed
     *
     * @return SlidingPaginationInterface<int, T>
     */
    public function paginateQuery(QueryBuilder $queryBuilder, string $entityClass, Request $request): SlidingPaginationInterface
    {
        /** @var SlidingPaginationInterface<int, T> $pagination */
        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $this->page($request),
            $this->perPage($request),
            self::SORTING_OFF,
        );

        return $pagination;
    }

    /**
     * @template TValue
     *
     * @param list<TValue> $items
     *
     * @return SlidingPaginationInterface<int, TValue>
     */
    public function paginateArray(array $items, Request $request): SlidingPaginationInterface
    {
        /** @var SlidingPaginationInterface<int, TValue> $pagination */
        $pagination = $this->paginator->paginate(
            $items,
            $this->page($request),
            $this->perPage($request),
            self::SORTING_OFF,
        );

        return $pagination;
    }

    /**
     * What the headers should render: which keys are clickable, which one is
     * active, and which way it points.
     *
     * @param array<string, mixed> $sortMap only its keys matter here — the column
     *                                      keys this view offers
     */
    public function sortState(Request $request, array $sortMap): SortState
    {
        return new SortState(
            array_keys($sortMap),
            $this->sortKey($request, $sortMap),
            $this->sortDirection($request),
        );
    }

    /**
     * Re-orders $queryBuilder in place. A no-op without a valid `sort`, which
     * is what leaves each view's createOrderedBy…QueryBuilder() default intact.
     *
     * @param array<string, non-empty-list<string>> $sortMap column key => DQL field(s).
     *                                                       Because the map IS the
     *                                                       whitelist, no user-supplied
     *                                                       string ever reaches DQL
     */
    public function applySort(QueryBuilder $queryBuilder, Request $request, array $sortMap): void
    {
        $key = $this->sortKey($request, $sortMap);

        if (null === $key) {
            return;
        }

        // The repository's own ORDER BY is kept as the tie-break rather than
        // replaced. Sorting Volunteers by Status drops every row into two
        // buckets; with no secondary order SQLite is free to hand back a row
        // on page 2 that was already on page 1.
        /** @var list<OrderBy> $tieBreak */
        $tieBreak = $queryBuilder->getDQLPart('orderBy');
        $queryBuilder->resetDQLPart('orderBy');

        // Uppercased on the way into DQL: the constants are lowercase because
        // that is what goes in a URL, but Doctrine echoes the direction
        // verbatim and the repositories' own ORDER BY reads "ASC".
        $direction = strtoupper($this->sortDirection($request));

        foreach ($sortMap[$key] as $field) {
            $queryBuilder->addOrderBy($field, $direction);
        }

        foreach ($tieBreak as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }
    }

    /**
     * The array equivalent, for /reports — which paginates an in-memory
     * breakdown, not a query. Sorts the WHOLE list, so the caller must apply
     * it before paginateArray() rather than to a page.
     *
     * @template TRow of array<string, mixed>
     *
     * @param list<TRow>            $rows
     * @param array<string, string> $sortMap column key => array key
     *
     * @return list<TRow>
     */
    public function sortArray(array $rows, Request $request, array $sortMap): array
    {
        $key = $this->sortKey($request, $sortMap);

        if (null === $key) {
            return $rows;
        }

        $field = $sortMap[$key];
        $descending = SortState::DESC === $this->sortDirection($request);

        // usort has been stable since PHP 8.0, so rows that tie keep the order
        // the caller handed them in — ActivitySummaryCalculator's own totalDays
        // ordering. That is this side's tie-break, for free.
        usort(
            $rows,
            /**
             * @param array<string, mixed> $a
             * @param array<string, mixed> $b
             */
            static function (array $a, array $b) use ($field, $descending): int {
                $left = $a[$field] ?? null;
                $right = $b[$field] ?? null;

                // Nulls last in BOTH directions: an empty "Most recent" cell is
                // missing data, not a small value, and a reader expects those
                // rows at the bottom whichever way the column points.
                if (null === $left || null === $right) {
                    return (null === $left ? 1 : 0) <=> (null === $right ? 1 : 0);
                }

                $comparison = $left <=> $right;

                return $descending ? -$comparison : $comparison;
            },
        );

        return $rows;
    }

    public function perPage(Request $request): int
    {
        $requested = $request->query->get('perPage');

        if ('all' === $requested) {
            return self::ALL_PER_PAGE;
        }

        // Cast rather than InputBag::getInt(), which throws a BadRequestException
        // on anything non-numeric — `?perPage=abc` would then be a 400 instead of
        // the harmless fallback this class promises. `(int) 'abc'` is 0, which is
        // not on the list, so it lands on the default like any other bad value.
        return \in_array((int) $requested, self::PER_PAGE_OPTIONS, true)
            ? (int) $requested
            : self::DEFAULT_PER_PAGE;
    }

    public function page(Request $request): int
    {
        // Same reason as perPage() for casting instead of getInt().
        return max(1, (int) $request->query->get('page'));
    }

    /**
     * @param array<string, mixed> $sortMap
     */
    private function sortKey(Request $request, array $sortMap): ?string
    {
        // Read through all() rather than InputBag::get(), which throws a
        // BadRequestException on a non-scalar — `?sort[]=x` would then be a 400
        // instead of the harmless fallback this class promises.
        $requested = $request->query->all()['sort'] ?? null;

        return \is_string($requested) && \array_key_exists($requested, $sortMap)
            ? $requested
            : null;
    }

    private function sortDirection(Request $request): string
    {
        // Same reason as sortKey() for going through all().
        $requested = $request->query->all()['direction'] ?? null;

        // Ascending unless descending was asked for explicitly: the first click
        // on a header sorts ascending, so that is the safer default for a
        // hand-edited or truncated URL too.
        return \is_string($requested) && SortState::DESC === strtolower($requested)
            ? SortState::DESC
            : SortState::ASC;
    }
}
