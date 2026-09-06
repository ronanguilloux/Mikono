<?php

declare(strict_types=1);

namespace App\Tests\Integration\Usage;

use App\Usage\AccessLogReader;
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
    /** @return array{exists: bool, rows: list<array<string, mixed>>, views: int, rejected: int, clientErrors: int, serverErrors: int, mobile: int, prefetched: int, unrouted: int, since: ?\DateTimeImmutable, until: ?\DateTimeImmutable} */
    private static function read(string $file = 'access-log-sample.ndjson'): array
    {
        self::bootKernel();

        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return (new AccessLogReader($router, __DIR__ . '/' . $file))->read();
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
     * Every successful write in this app redirects, so a POST answering 2xx is
     * a redisplayed form. This is the substitute for a 422 count, which this
     * app cannot provide — see ADR 0021.
     */
    #[Test]
    public function aPostAnsweringTwoHundredCountsAsARejectedSubmission(): void
    {
        $report = self::read();
        $row = self::row($report, 'POST', '/volunteers/new');

        self::assertSame(2, $row['views']);
        self::assertSame(1, $row['rejected'], 'the 302 saved, the 200 was redisplayed');
        self::assertSame(1, $report['rejected']);
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
     * Counting every non-GET 2xx as rejected reported the recorder's own
     * traffic as a wall of failed submissions.
     */
    #[Test]
    public function aTwoOhFourIsNotARejectedSubmission(): void
    {
        // The 200 on /volunteers/new is the only rejection in the fixture; the
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
