<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\QueryBuilder;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The one place `page` and `perPage` are read off the query string, so the
 * seven list views don't each carry their own whitelist. See ADR 0009.
 *
 * Bad input never 404s. An unknown `perPage` falls back to the default, a
 * `page` below 1 clamps to 1, and a `page` past the end serves the last page
 * (`page_out_of_range: fix` in config/packages/knp_paginator.yaml). There is
 * one non-technical user working from bookmarked URLs — landing on the last
 * page beats a dead end, and none of these params are worth an error screen.
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
        );

        return $pagination;
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
}
