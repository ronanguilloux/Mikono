<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pagination;

use App\Pagination\ListPaginator;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class ListPaginatorTest extends KernelTestCase
{
    #[Test]
    public function defaultsToTwentyFiveRowsPerPage(): void
    {
        self::assertSame(25, $this->paginator()->perPage(new Request()));
    }

    #[Test]
    #[DataProvider('whitelistedPageSizes')]
    public function acceptsEveryWhitelistedPageSize(int $requested): void
    {
        self::assertSame($requested, $this->paginator()->perPage($this->requestWith(['perPage' => (string) $requested])));
    }

    /** @return iterable<array{int}> */
    public static function whitelistedPageSizes(): iterable
    {
        foreach (ListPaginator::PER_PAGE_OPTIONS as $option) {
            yield [$option];
        }
    }

    #[Test]
    public function mapsAllToTheFiniteCeiling(): void
    {
        self::assertSame(
            ListPaginator::ALL_PER_PAGE,
            $this->paginator()->perPage($this->requestWith(['perPage' => 'all'])),
        );
    }

    #[Test]
    #[DataProvider('rejectedPageSizes')]
    public function fallsBackToTheDefaultForAnyUnlistedPageSize(string $requested): void
    {
        self::assertSame(25, $this->paginator()->perPage($this->requestWith(['perPage' => $requested])));
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedPageSizes(): iterable
    {
        yield 'off-list number' => ['7'];
        yield 'non-numeric' => ['abc'];
        yield 'negative' => ['-1'];
        yield 'zero' => ['0'];
        yield 'empty' => [''];
        // Guards the ALL_PER_PAGE ceiling: only the literal "all" reaches it,
        // so nobody can ask for an arbitrarily large page by URL.
        yield 'the ceiling itself' => [(string) ListPaginator::ALL_PER_PAGE];
    }

    #[Test]
    public function defaultsToTheFirstPage(): void
    {
        self::assertSame(1, $this->paginator()->page(new Request()));
    }

    #[Test]
    #[DataProvider('nonPositivePages')]
    public function clampsANonPositivePageToTheFirst(string $requested): void
    {
        self::assertSame(1, $this->paginator()->page($this->requestWith(['page' => $requested])));
    }

    /** @return iterable<string, array{string}> */
    public static function nonPositivePages(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-3'];
        yield 'non-numeric' => ['abc'];
    }

    #[Test]
    public function passesAValidPageThrough(): void
    {
        self::assertSame(4, $this->paginator()->page($this->requestWith(['page' => '4'])));
    }

    /**
     * The windowed page range is the piece ADR 0009 bought the dependency for
     * and the piece most likely to be quietly wrong, so it is asserted against
     * the reviewed design directly rather than through a controller: 1675 rows
     * at 25 a page is 67 pages, which is exactly the large-scale case the
     * 2026-08-28 mockup shows.
     */
    #[Test]
    #[DataProvider('windowedPageRanges')]
    public function rendersTheReviewedWindowedControls(int $page, string $expected): void
    {
        self::assertSame($expected, $this->renderControlsFor($page, 1675));
    }

    /** @return iterable<string, array{int, string}> */
    public static function windowedPageRanges(): iterable
    {
        // Two sentinels at the tail and one at the head, per the mockup.
        yield 'middle of the range' => [33, '‹ 1 … 32 33 34 … 66 67 ›'];
        // At the start the head sentinel is already inside the window, so it
        // must not be repeated as "1 … 1 2 3".
        yield 'first page' => [1, '‹ 1 2 3 … 66 67 ›'];
        yield 'second page' => [2, '‹ 1 2 3 … 66 67 ›'];
        // Approaching the end, the tail sentinels are inside the window and
        // the trailing ellipsis has to disappear with them.
        yield 'last page' => [67, '‹ 1 … 65 66 67 ›'];
        yield 'one before last' => [66, '‹ 1 … 65 66 67 ›'];
        // A single hidden page collapses to the number itself, never "1 … 2".
        yield 'no gap to collapse at the head' => [3, '‹ 1 2 3 4 … 66 67 ›'];
    }

    #[Test]
    public function rendersNothingAtAllForASinglePage(): void
    {
        self::assertSame('', $this->renderControlsFor(1, 10));
    }

    #[Test]
    public function rendersNothingForAnEmptyList(): void
    {
        // Zero rows makes the bundle's page range compute to [1, 0]; unguarded,
        // that renders a link to page "0".
        self::assertSame('', $this->renderControlsFor(1, 0));
    }

    private function renderControlsFor(int $page, int $totalRows): string
    {
        $pagination = $this->paginator()->paginateArray(
            range(1, $totalRows),
            $this->requestWith(['page' => (string) $page]),
        );

        // The bundle's own pagination type, not just the base one: getRoute()
        // and getPaginationData() are what the controls template reads.
        self::assertInstanceOf(SlidingPaginationInterface::class, $pagination);

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('pagination/tailwind.html.twig', array_merge(
            $pagination->getPaginationData(),
            [
                'route' => 'report_index',
                'query' => [],
                'options' => $pagination->getPaginatorOptions(),
            ],
        ));

        // Collapse the markup to the sequence a reader would see.
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }

    /** @param array<string, string> $query */
    private function requestWith(array $query): Request
    {
        return new Request($query);
    }

    /**
     * Built by hand rather than pulled from the container: every method under
     * test here only reads the query string, and constructing it directly
     * keeps these cases green regardless of which controllers inject the
     * service (an unused private service is compiled away entirely).
     */
    private function paginator(): ListPaginator
    {
        self::bootKernel();
        $knpPaginator = self::getContainer()->get('knp_paginator');
        self::assertInstanceOf(PaginatorInterface::class, $knpPaginator);

        return new ListPaginator($knpPaginator);
    }
}
