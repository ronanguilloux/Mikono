<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pagination;

use App\Pagination\ListPaginator;
use App\Pagination\SortState;
use App\Repository\VolunteerRepository;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class ListPaginatorTest extends KernelTestCase
{
    /**
     * VolunteerController's real map, which is the interesting one: `name`
     * covers the multi-field case and `status` the single-field one.
     *
     * @var array<string, non-empty-list<string>>
     */
    private const array SORT_MAP = [
        'name' => ['v.lastName', 'v.firstName'],
        'status' => ['v.isActive'],
    ];

    /** The DQL VolunteerRepository::createOrderedByNameQueryBuilder() produces untouched. */
    private const string DEFAULT_DQL = 'SELECT v FROM App\Entity\Volunteer v ORDER BY v.lastName ASC, v.firstName ASC';

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

    #[Test]
    public function reportsEveryMappedColumnAsSortable(): void
    {
        self::assertSame(['name', 'status'], $this->sortState([])->sortableKeys);
    }

    #[Test]
    public function resolvesAMappedSortKey(): void
    {
        self::assertSame('status', $this->sortState(['sort' => 'status'])->activeKey);
    }

    /**
     * The map is the whitelist, so anything not in it has to fall through to
     * the view's own order rather than reaching DQL or erroring.
     */
    #[Test]
    #[DataProvider('unusableSortParams')]
    public function hasNoActiveColumnForAnUnusableSort(mixed $requested): void
    {
        self::assertNull($this->sortState(null === $requested ? [] : ['sort' => $requested])->activeKey);
    }

    /** @return iterable<string, array{mixed}> */
    public static function unusableSortParams(): iterable
    {
        yield 'absent' => [null];
        yield 'not in the map' => ['phone'];
        yield 'empty' => [''];
        // Reaches ListPaginator through query->all(), never InputBag::get(),
        // which would turn `?sort[]=name` into a 400.
        yield 'an array' => [['name']];
        yield 'a DQL path, in case anyone tries' => ['v.lastName'];
    }

    #[Test]
    #[DataProvider('sortDirections')]
    public function readsDescendingOnlyWhenItIsAskedFor(?string $requested, string $expected): void
    {
        $query = ['sort' => 'name'] + (null === $requested ? [] : ['direction' => $requested]);

        self::assertSame($expected, $this->sortState($query)->direction);
    }

    /** @return iterable<string, array{?string, string}> */
    public static function sortDirections(): iterable
    {
        yield 'descending' => ['desc', SortState::DESC];
        yield 'descending, shouted' => ['DESC', SortState::DESC];
        yield 'ascending' => ['asc', SortState::ASC];
        yield 'absent' => [null, SortState::ASC];
        yield 'junk' => ['sideways', SortState::ASC];
        yield 'empty' => ['', SortState::ASC];
    }

    /**
     * Clicking a header sorts ascending first and flips only on a second
     * click; a different column always starts over at ascending rather than
     * inheriting the current direction.
     */
    #[Test]
    public function flipsOnlyTheActiveColumn(): void
    {
        $ascending = $this->sortState(['sort' => 'name', 'direction' => 'asc']);

        self::assertSame(SortState::DESC, $ascending->nextDirectionFor('name'));
        self::assertSame(SortState::ASC, $ascending->nextDirectionFor('status'));

        $descending = $this->sortState(['sort' => 'name', 'direction' => 'desc']);

        self::assertSame(SortState::ASC, $descending->nextDirectionFor('name'));
    }

    #[Test]
    public function announcesTheSortedColumnToScreenReaders(): void
    {
        $state = $this->sortState(['sort' => 'name', 'direction' => 'desc']);

        self::assertSame('descending', $state->ariaSortFor('name'));
        self::assertNull($state->ariaSortFor('status'));
    }

    #[Test]
    public function leavesTheViewsOwnOrderAloneWithoutASort(): void
    {
        self::assertSame(self::DEFAULT_DQL, $this->dqlAfterSort([]));
    }

    /**
     * The same must hold for a junk `sort`, byte for byte: a bookmarked URL
     * with a stale column name shows the default order, never an error.
     */
    #[Test]
    public function leavesTheViewsOwnOrderAloneForAnUnknownSort(): void
    {
        self::assertSame(self::DEFAULT_DQL, $this->dqlAfterSort(['sort' => 'nonsense', 'direction' => 'desc']));
    }

    #[Test]
    public function putsTheRequestedColumnFirstAndKeepsTheDefaultOrderAsTieBreak(): void
    {
        // Without the trailing tie-break, every row falls into one of two
        // isActive buckets and SQLite may repeat a page-1 row on page 2.
        self::assertSame(
            'SELECT v FROM App\Entity\Volunteer v ORDER BY v.isActive ASC, v.lastName ASC, v.firstName ASC',
            $this->dqlAfterSort(['sort' => 'status']),
        );
    }

    #[Test]
    public function appliesEveryFieldOfAMultiFieldColumn(): void
    {
        // `name` has no single column behind it — getFullName() is lastName
        // plus firstName, and both have to flip together.
        self::assertSame(
            'SELECT v FROM App\Entity\Volunteer v ORDER BY v.lastName DESC, v.firstName DESC, v.lastName ASC, v.firstName ASC',
            $this->dqlAfterSort(['sort' => 'name', 'direction' => 'desc']),
        );
    }

    #[Test]
    public function leavesAnArrayOfRowsAloneWithoutASort(): void
    {
        $sorted = $this->paginator()->sortArray(self::summaries(), new Request(), ['count' => 'count']);

        self::assertSame('Ada Bea Chris Dee', implode(' ', array_column($sorted, 'label')));
    }

    #[Test]
    #[DataProvider('arraySortOrders')]
    public function sortsAnArrayOfRowsInBothDirections(string $direction, string $expected): void
    {
        $sorted = $this->paginator()->sortArray(
            self::summaries(),
            $this->requestWith(['sort' => 'count', 'direction' => $direction]),
            ['count' => 'count'],
        );

        self::assertSame($expected, implode(' ', array_column($sorted, 'label')));
    }

    /** @return iterable<string, array{string, string}> */
    public static function arraySortOrders(): iterable
    {
        yield 'ascending' => ['asc', 'Chris Bea Ada Dee'];
        yield 'descending' => ['desc', 'Dee Ada Bea Chris'];
    }

    /**
     * A "—" cell is missing data, not a small value, so those rows belong at
     * the bottom whichever way the column points. /reports' `mostRecent` is
     * the only nullable column today.
     */
    #[Test]
    #[DataProvider('nullSortDirections')]
    public function keepsRowsWithNoValueLastInBothDirections(string $direction): void
    {
        $sorted = $this->paginator()->sortArray(
            self::summaries(),
            $this->requestWith(['sort' => 'mostRecent', 'direction' => $direction]),
            ['mostRecent' => 'mostRecent'],
        );

        $labels = array_column($sorted, 'label');

        self::assertSame('Chris', array_pop($labels));
    }

    /** @return iterable<string, array{string}> */
    public static function nullSortDirections(): iterable
    {
        yield 'ascending' => ['asc'];
        yield 'descending' => ['desc'];
    }

    /**
     * usort is stable as of PHP 8.0, which is what makes the caller's own
     * order — ActivitySummaryCalculator's totalDays ranking — the tie-break
     * here, the way the repository's ORDER BY is on the query side.
     */
    #[Test]
    public function keepsTheCallersOrderForRowsThatTie(): void
    {
        $sorted = $this->paginator()->sortArray(
            self::summaries(),
            $this->requestWith(['sort' => 'totalDays']),
            ['totalDays' => 'totalDays'],
        );

        // Ada and Bea both total 2.0 days, and Ada was handed in first.
        self::assertSame('Dee Ada Bea Chris', implode(' ', array_column($sorted, 'label')));
    }

    /**
     * Four rows in the shape ReportController paginates, deliberately not in
     * any of the orders under test: one tie on totalDays, one null date.
     *
     * @return list<array{label: string, count: int, totalDays: float, mostRecent: ?\DateTimeImmutable}>
     */
    private static function summaries(): array
    {
        return [
            ['label' => 'Ada', 'count' => 5, 'totalDays' => 2.0, 'mostRecent' => new \DateTimeImmutable('2026-03-01')],
            ['label' => 'Bea', 'count' => 3, 'totalDays' => 2.0, 'mostRecent' => new \DateTimeImmutable('2026-01-01')],
            ['label' => 'Chris', 'count' => 1, 'totalDays' => 9.0, 'mostRecent' => null],
            ['label' => 'Dee', 'count' => 9, 'totalDays' => 1.0, 'mostRecent' => new \DateTimeImmutable('2026-02-01')],
        ];
    }

    /** @param array<string, mixed> $query */
    private function sortState(array $query): SortState
    {
        return $this->paginator()->sortState(new Request($query), self::SORT_MAP);
    }

    /**
     * Asserted as DQL rather than through a controller: the ORDER BY is the
     * whole point, and reading it back off the QueryBuilder says exactly what
     * applySort() did without a database round trip.
     *
     * @param array<string, mixed> $query
     */
    private function dqlAfterSort(array $query): string
    {
        self::bootKernel();
        $container = self::getContainer();

        $volunteers = $container->get(VolunteerRepository::class);
        self::assertInstanceOf(VolunteerRepository::class, $volunteers);

        $knpPaginator = $container->get('knp_paginator');
        self::assertInstanceOf(PaginatorInterface::class, $knpPaginator);

        $queryBuilder = $volunteers->createOrderedByNameQueryBuilder();
        (new ListPaginator($knpPaginator))->applySort($queryBuilder, new Request($query), self::SORT_MAP);

        return $queryBuilder->getDQL();
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
