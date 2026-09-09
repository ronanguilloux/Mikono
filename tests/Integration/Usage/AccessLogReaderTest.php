<?php

declare(strict_types=1);

namespace App\Tests\Integration\Usage;

use App\Usage\AccessLogReader;
use App\Usage\UsageDateRange;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Everything the /usage screen claims, asserted against a fixture rather than
 * against whatever the dev container happened to serve this morning.
 *
 * KernelTestCase because the reader needs the real router to turn a logged
 * URI into a route pattern — but no database, so no #[ResetDatabase] and no
 * Foundry here.
 *
 * The fixture beside this file is a real Caddy JSON log shape, headers as
 * one-element lists included, and its last line is deliberately truncated.
 */
final class AccessLogReaderTest extends KernelTestCase
{
    /**
     * The second fixture, access-log-days-sample.ndjson, spans three days —
     * 29, 30 and 31 August 2026 — because the main one is a 95-second window
     * and so cannot show a date range at all. Re-dating that one instead would
     * have invalidated every count assertion standing on it.
     */
    private const string DAYS_FIXTURE = 'access-log-days-sample.ndjson';

    /** @return array{exists: bool, rows: list<array<string, mixed>>, views: int, rejected: int, clientErrors: int, serverErrors: int, mobile: int, prefetched: int, unrouted: int, since: ?\DateTimeImmutable, until: ?\DateTimeImmutable} */
    private static function read(string $file = 'access-log-sample.ndjson', ?UsageDateRange $range = null): array
    {
        self::bootKernel();

        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return (new AccessLogReader($router, __DIR__ . '/' . $file))->read($range);
    }

    /** Explicit dates, never a preset: a preset-based expectation would rot by tomorrow. */
    private static function days(?string $from, ?string $to): UsageDateRange
    {
        return new UsageDateRange(
            null === $from ? null : new \DateTimeImmutable($from),
            null === $to ? null : new \DateTimeImmutable($to),
        );
    }

    /**
     * The contract RouteSmokeTest depends on: CI has never run Caddy, so the
     * log file does not exist there and /usage still has to render.
     */
    #[Test]
    public function aMissingLogFileIsAnEmptyReportRatherThanAnError(): void
    {
        $report = self::read('no-such-file.ndjson');

        self::assertFalse($report['exists']);
        self::assertSame([], $report['rows']);
        self::assertSame(0, $report['views']);
        self::assertNull($report['since']);
    }

    #[Test]
    public function recordIdsCollapseIntoOneRowPerRoutePattern(): void
    {
        $report = self::read();
        $row = self::row($report, 'GET', '/volunteers/{id}/edit');

        // Two different volunteers edited, one row.
        self::assertSame(2, $row['views']);
    }

    /**
     * The data-protection half of ADR 0018's objection: a URL like
     * /volunteers/12/edit names a record, and this screen must not.
     */
    #[Test]
    public function noRecordIdentifierSurvivesIntoTheReport(): void
    {
        $paths = array_column(self::read()['rows'], 'path');

        self::assertNotContains('/volunteers/12/edit', $paths);
        self::assertNotContains('/volunteers/13/edit', $paths);
        self::assertContains('/volunteers/{id}/edit', $paths);
    }

    /**
     * Turbo Drive fetches a link on hover. Counting those would report screens
     * nobody chose to open.
     */
    #[Test]
    public function hoverPrefetchesAreExcludedFromTheCounts(): void
    {
        $report = self::read();

        self::assertSame(1, $report['prefetched']);
        // The prefetched line was the only hit on /volunteers/{id}.
        self::assertNull(self::maybeRow($report, 'GET', '/volunteers/{id}'));
    }

    /**
     * Assets and favicon have no route, which is the whole asset filter — no
     * hand-kept ignore list to drift out of date.
     */
    #[Test]
    public function requestsWithNoRouteBehindThemAreCountedButNotListed(): void
    {
        self::assertNotContains('/favicon.ico', array_column(self::read()['rows'], 'path'));
    }

    /**
     * Symfony's AbstractController::render() answers 422 when a submitted form
     * among the parameters is invalid, so a redisplayed form is measured, not
     * inferred — see ADR 0021.
     */
    #[Test]
    public function aFourTwentyTwoCountsAsARejectedSubmission(): void
    {
        $report = self::read();
        $row = self::row($report, 'POST', '/volunteers/new');

        self::assertSame(2, $row['views']);
        self::assertSame(1, $row['rejected'], 'the 302 saved, the 422 was redisplayed');
        self::assertSame(1, $report['rejected']);
    }

    /**
     * The 422 branch sits above the >= 400 one, and this is what says so. Move
     * it below and every assertion here still passes except these: `rejected`
     * goes to a constant zero and `clientErrors` quietly absorbs every
     * rejection, which is what the screen shipped with.
     */
    #[Test]
    public function aRejectedSubmissionIsNotAlsoCountedAsAClientError(): void
    {
        $report = self::read();

        self::assertSame(0, self::row($report, 'POST', '/volunteers/new')['clientErrors']);
        // The only 4xx left in the fixture is /favicon.ico, which has no route
        // behind it and so never reaches a bucket at all.
        self::assertSame(0, $report['clientErrors']);
    }

    /**
     * /usage/event is the screen's own recorder — every in-page action posts
     * to it — so listing it would make this feature look like one of the app's
     * busiest pages and report itself as measuring itself.
     */
    #[Test]
    public function theUsageRecorderDoesNotAppearInItsOwnReport(): void
    {
        $report = self::read();

        self::assertNull(self::maybeRow($report, 'POST', '/usage/event'));
        // Counted as unrouted rather than silently vanishing: assets, favicon
        // and this one.
        self::assertSame(3, $report['unrouted']);
    }

    /**
     * A 204 is a successful write with nothing to say, not a redisplayed form.
     * Trivial against an exact-422 rule, and kept for the version of the rule
     * that isn't: counting every non-GET 2xx as rejected reported the
     * recorder's own traffic as a wall of failed submissions.
     */
    #[Test]
    public function aTwoOhFourIsNotARejectedSubmission(): void
    {
        // The 422 on /volunteers/new is the only rejection in the fixture; the
        // 204 on /usage/event and the 302 are both successes.
        self::assertSame(1, self::read()['rejected']);
    }

    #[Test]
    public function serverErrorsAreCountedPerRoute(): void
    {
        $report = self::read();

        self::assertSame(1, self::row($report, 'GET', '/reports')['serverErrors']);
        self::assertSame(1, $report['serverErrors']);
    }

    #[Test]
    public function mobileRequestsAreCountedFromTheClientHint(): void
    {
        $report = self::read();

        self::assertSame(1, $report['mobile']);
        self::assertSame(1, self::row($report, 'GET', '/activities')['mobile']);
    }

    #[Test]
    public function p95IsTheSlowEndOfTheRouteNotItsAverage(): void
    {
        // Two samples, 0.1s and 0.5s: nearest-rank p95 is the slower one.
        self::assertEqualsWithDelta(0.5, self::row(self::read(), 'GET', '/volunteers/{id}/edit')['p95'], 0.0001);
    }

    /**
     * Caddy appends to this file while the reader walks it, so the last line
     * is routinely half-written.
     */
    #[Test]
    public function aTruncatedFinalLineIsSkippedRatherThanFatal(): void
    {
        // Reaching an assertion at all is the point: read() did not throw.
        self::assertTrue(self::read()['exists']);
    }

    /**
     * The whole point of the filter being applied in the streaming pass rather
     * than to the finished rows: p95 and the per-row counters are computed as
     * the file is walked, so a range that excludes the slow sample has to
     * change them. Filter the rows afterwards instead and this is the
     * assertion that fails — 2.5 would survive into a window it happened
     * outside of.
     */
    #[Test]
    public function aRangeChangesThePerRowCountersAndNotJustWhichRowsAppear(): void
    {
        $all = self::read(self::DAYS_FIXTURE);
        $later = self::read(self::DAYS_FIXTURE, self::days('2026-08-30', '2026-08-31'));

        // The 2.5s edit was on the 29th, the 0.1s one on the 30th.
        self::assertSame(2, self::row($all, 'GET', '/volunteers/{id}/edit')['views']);
        self::assertEqualsWithDelta(2.5, self::row($all, 'GET', '/volunteers/{id}/edit')['p95'], 0.0001);

        self::assertSame(1, self::row($later, 'GET', '/volunteers/{id}/edit')['views']);
        self::assertEqualsWithDelta(0.1, self::row($later, 'GET', '/volunteers/{id}/edit')['p95'], 0.0001);
    }

    /**
     * The range check sits above the prefetch and unrouted branches, so the
     * "hover-prefetches ignored" and "requests for assets" lines cover the
     * same window as the table rather than the whole file.
     */
    #[Test]
    public function aRangeBoundsTheIgnoredRequestsToo(): void
    {
        $all = self::read(self::DAYS_FIXTURE);
        // The asset and the prefetch are both on the 29th; the favicon is on the 31st.
        $later = self::read(self::DAYS_FIXTURE, self::days('2026-08-30', null));

        self::assertSame(1, $all['prefetched']);
        self::assertSame(2, $all['unrouted']);

        self::assertSame(0, $later['prefetched']);
        self::assertSame(1, $later['unrouted']);
    }

    #[Test]
    public function bothEndsOfARangeAreInclusiveWholeDays(): void
    {
        // One request on the 29th survives the filter, and it is the whole day
        // rather than midnight: the entry is logged at 14:20.
        $oneDay = self::read(self::DAYS_FIXTURE, self::days('2026-08-29', '2026-08-29'));

        self::assertSame(1, $oneDay['views']);
        self::assertSame('2026-08-29', $oneDay['until']?->format('Y-m-d'));
    }

    #[Test]
    public function anEmptyWindowIsAnEmptyReportRatherThanTheWholeFile(): void
    {
        $report = self::read(self::DAYS_FIXTURE, self::days('2026-09-01', '2026-09-30'));

        self::assertTrue($report['exists'], 'the log is there, it simply says nothing about September');
        self::assertSame([], $report['rows']);
        self::assertSame(0, $report['views']);
        self::assertNull($report['since']);
    }

    #[Test]
    public function theReportKnowsTheWindowItCovers(): void
    {
        $report = self::read();

        self::assertNotNull($report['since']);
        self::assertNotNull($report['until']);
        self::assertLessThan($report['until'], $report['since']);
    }

    /**
     * @param array{rows: list<array<string, mixed>>, ...} $report
     *
     * @return array<string, mixed>
     */
    private static function row(array $report, string $method, string $path): array
    {
        $row = self::maybeRow($report, $method, $path);
        self::assertNotNull($row, "expected a row for {$method} {$path}");

        return $row;
    }

    /**
     * @param array{rows: list<array<string, mixed>>, ...} $report
     *
     * @return array<string, mixed>|null
     */
    private static function maybeRow(array $report, string $method, string $path): ?array
    {
        foreach ($report['rows'] as $row) {
            if ($row['method'] === $method && $row['path'] === $path) {
                return $row;
            }
        }

        return null;
    }
}
