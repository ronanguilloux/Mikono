<?php

declare(strict_types=1);

namespace App\Tests\Integration\Usage;

use App\Usage\UsageDateRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The /usage date range, resolved from the query string.
 *
 * Plain TestCase, no kernel and no database: this class reads a Request and
 * does date arithmetic, nothing more. It lives beside AccessLogReaderTest
 * because it is the same feature, and tests/ has no Unit directory.
 *
 * Every assertion about a preset goes through an explicit $today — the maths
 * is relative, so a test that let it default would pass today and fail in
 * January.
 */
final class UsageDateRangeTest extends TestCase
{
    private const string TODAY = '2026-09-09';

    private static function fromQuery(string $query): UsageDateRange
    {
        return UsageDateRange::fromRequest(
            Request::create('/usage?' . $query),
            new \DateTimeImmutable(self::TODAY),
        );
    }

    /**
     * The default is visible on screen (the template ticks range.preset), so
     * it has to be a resolved value rather than an implied absence of one.
     */
    #[Test]
    public function noQueryStringOpensOnTheDefaultPreset(): void
    {
        $range = self::fromQuery('');

        self::assertSame(UsageDateRange::DEFAULT_PRESET, $range->preset());
        self::assertSame('Last 7 days', $range->label());
        self::assertTrue($range->isBounded());
    }

    /** Seven dates on a calendar, today included — which is what the label promises. */
    #[Test]
    public function aSevenDayPresetCoversSevenDaysIncludingToday(): void
    {
        $range = self::fromQuery('range=7d');

        self::assertSame('2026-09-03', $range->from()?->format('Y-m-d'));
        self::assertSame(self::TODAY, $range->to()?->format('Y-m-d'));
        // The upper bound is exclusive midnight of the NEXT day, or everything
        // logged today after 00:00 would fall outside the window it belongs to.
        self::assertSame('2026-09-10 00:00:00', $range->untilExclusive()?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function yearToDateStartsOnTheFirstOfJanuary(): void
    {
        self::assertSame('2026-01-01', self::fromQuery('range=ytd')->from()?->format('Y-m-d'));
    }

    /**
     * 'all' is a listed preset rather than the absence of one: with a filtered
     * default, the unfiltered view has to stay reachable from the control.
     */
    #[Test]
    public function theAllTimePresetIsUnbounded(): void
    {
        $range = self::fromQuery('range=all');

        self::assertFalse($range->isBounded());
        self::assertNull($range->from());
        self::assertSame('all', $range->preset());
    }

    #[Test]
    public function anExplicitPairWinsOverAPresetAndTicksNoPreset(): void
    {
        $range = self::fromQuery('range=90d&from=2026-08-01&to=2026-08-31');

        self::assertNull($range->preset(), 'a custom pair leaves every preset link unticked');
        self::assertSame('2026-08-01', $range->from()?->format('Y-m-d'));
        self::assertSame('2026-08-31', $range->to()?->format('Y-m-d'));
        self::assertSame('1 Aug 2026 – 31 Aug 2026', $range->label());
    }

    /** Boxes filled in the order that made sense to the reader, not an error screen. */
    #[Test]
    public function aReversedPairSwapsRatherThanReportingNothing(): void
    {
        $range = self::fromQuery('from=2026-08-31&to=2026-08-01');

        self::assertSame('2026-08-01', $range->from()?->format('Y-m-d'));
        self::assertSame('2026-08-31', $range->to()?->format('Y-m-d'));
    }

    #[Test]
    public function oneEndOfACustomPairIsEnough(): void
    {
        $open = self::fromQuery('from=2026-08-01');

        self::assertSame('2026-08-01', $open->from()?->format('Y-m-d'));
        self::assertNull($open->untilExclusive());
        self::assertTrue($open->isBounded());
        self::assertTrue($open->contains(new \DateTimeImmutable('2030-01-01')));
    }

    /**
     * Bad input lands on the DEFAULT preset, not on unbounded: a typo'd range
     * must not silently widen the window past what the control says is on
     * screen. And it must never 400 — CLAUDE.md's rule for every query param
     * this app reads, because there is one non-technical user working from
     * bookmarked URLs.
     *
     * @param string $query the malformed query string
     */
    #[Test]
    #[DataProvider('malformedQueries')]
    public function malformedInputFallsBackToTheDefaultPreset(string $query): void
    {
        $range = self::fromQuery($query);

        self::assertSame(UsageDateRange::DEFAULT_PRESET, $range->preset(), $query);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedQueries(): iterable
    {
        yield 'unknown preset' => ['range=abc'];
        yield 'unparseable dates' => ['from=nope&to=also-nope'];
        yield 'a date that looks like one' => ['from=2026-13-45'];
        // InputBag::get() throws a BadRequestException on these, which is why
        // this class reads through query->all() guarded by is_string().
        yield 'array range' => ['range[]=7d'];
        yield 'array from' => ['from[]=2026-08-01'];
        yield 'empty values' => ['range=&from=&to='];
    }

    /**
     * An entry whose `ts` did not parse belongs to no period, so counting it
     * would inflate whichever window is on screen. With no window at all there
     * is nothing to inflate.
     */
    #[Test]
    public function anUndatedEntryBelongsToNoBoundedWindow(): void
    {
        self::assertFalse(self::fromQuery('range=7d')->contains(null));
        self::assertTrue(self::fromQuery('range=all')->contains(null));
    }

    /**
     * The cache key is what stops one filter serving the previous filter's
     * report for up to a minute.
     */
    #[Test]
    public function equalWindowsShareACacheKeyAndDifferentOnesDoNot(): void
    {
        // Keyed on the resolved dates, so a preset and the pair it resolves to
        // are the same cached report.
        self::assertSame(
            self::fromQuery('range=7d')->cacheKey(),
            self::fromQuery('from=2026-09-03&to=2026-09-09')->cacheKey(),
        );
        self::assertNotSame(
            self::fromQuery('range=7d')->cacheKey(),
            self::fromQuery('range=30d')->cacheKey(),
        );
        self::assertSame('all', self::fromQuery('range=all')->cacheKey());
        // PSR-6 reserves {}()/\@: in a key.
        self::assertSame(1, preg_match('/^[A-Za-z0-9_.-]+$/', self::fromQuery('range=ytd')->cacheKey()));
    }
}
