<?php

declare(strict_types=1);

namespace App\Usage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Which screens actually get used, read from Caddy's own access log.
 *
 * This is ADR 0018's shell pipeline turned into a screen, over exactly the
 * same source — not a second measurement system, and emphatically not
 * third-party analytics. ADR 0018's "no gtag, no Plausible, no Matomo" is
 * unchanged; see ADR 0021 for what this adds and why.
 *
 * FrankenPHP *is* Caddy — the same container and the same process serves HTTP
 * and runs this code — so the log is a plain local file, with no sidecar, no
 * shipper and no second service to run.
 *
 * @phpstan-type UsageRow array{path: string, method: string, views: int, rejected: int, clientErrors: int, serverErrors: int, p95: float, mobile: int, lastSeen: ?\DateTimeImmutable}
 * @phpstan-type UsageReport array{exists: bool, rows: list<UsageRow>, views: int, rejected: int, clientErrors: int, serverErrors: int, mobile: int, prefetched: int, unrouted: int, since: ?\DateTimeImmutable, until: ?\DateTimeImmutable}
 */
final class AccessLogReader
{
    /**
     * Routes that are plumbing rather than screens, so listing them would only
     * describe the measuring. usage_event is /usage's own recorder: every
     * in-page action posts to it, so leaving it in would make this feature
     * look like one of the app's busiest pages.
     */
    private const array NOT_A_SCREEN = ['usage_event'];

    public function __construct(
        private readonly RouterInterface $router,
        // %kernel.logs_dir% is /app/var/log, the log_data volume compose.yaml
        // mounts. Injected as a string rather than read from a constant so the
        // integration test can point this at a fixture.
        #[Autowire('%kernel.logs_dir%/access.log')]
        private readonly string $path,
    ) {}

    /** @return UsageReport */
    public function read(): array
    {
        // Must never throw on a missing file. There is no Caddy log in CI, and
        // tests/Functional/RouteSmokeTest.php walks every GET route including
        // this one — a 500 here would fail the suite for the whole app.
        $handle = is_file($this->path) && is_readable($this->path)
            ? @fopen($this->path, 'r')
            : false;

        if (false === $handle) {
            return self::emptyReport();
        }

        // Route name => path pattern, so rows read "/volunteers/{id}/edit"
        // rather than one row per record id. That collapses the noise AND
        // drops the record identifiers ADR 0018 named as its data-protection
        // objection: nothing personally identifying reaches the screen.
        //
        // ponytail: getRouteCollection() re-reads the routing resources rather
        // than the compiled matcher. One call per cache miss on an admin-only
        // screen; RouteSmokeTest does the same thing.
        $collection = $this->router->getRouteCollection();
        $patterns = [];
        foreach ($collection as $name => $route) {
            $patterns[$name] = $route->getPath();
        }

        // Our own matcher over our own context rather than $router->match(),
        // which would mean mutating the shared RequestContext's method for
        // every line and restoring it afterwards.
        $context = new RequestContext();
        $matcher = new UrlMatcher($collection, $context);

        $buckets = [];
        $memo = [];
        $views = $rejected = $clientErrors = $serverErrors = $mobile = 0;
        $prefetched = $unrouted = 0;
        $since = $until = null;

        try {
            // fgets, not file_get_contents: at roll_size 10MiB the whole file
            // would otherwise sit in memory to answer a question about counts.
            while (false !== ($line = fgets($handle))) {
                $entry = json_decode($line, true);

                if (!\is_array($entry)) {
                    // A truncated final line is normal — Caddy appends to this
                    // file while we read it. Skip it, never fail on it.
                    continue;
                }

                $request = $entry['request'] ?? null;
                if (!\is_array($request)) {
                    continue;
                }

                $headers = \is_array($request['headers'] ?? null) ? $request['headers'] : [];

                // Turbo Drive prefetches on hover, so a link nobody clicked
                // still reaches the server. Counting those would inflate every
                // screen a reader merely passed over on the way to another.
                if (null !== self::header($headers, 'X-Sec-Purpose')) {
                    ++$prefetched;
                    continue;
                }

                $uri = self::str($request['uri'] ?? null);
                if (null === $uri) {
                    continue;
                }
                $method = self::str($request['method'] ?? null) ?? 'GET';

                $route = self::match($matcher, $context, $patterns, $memo, $method, $uri);

                // Nothing in this app serves it: /assets/*, /brand/*,
                // favicon.ico, or a genuine 404 on a path with no route. That
                // *is* the asset filter — no hand-kept ignore list to drift.
                if (null === $route) {
                    ++$unrouted;
                    continue;
                }

                $status = \is_int($entry['status'] ?? null) ? $entry['status'] : 0;
                $key = $method . ' ' . $route;

                $buckets[$key] ??= [
                    'path' => $route,
                    'method' => $method,
                    'views' => 0,
                    'rejected' => 0,
                    'clientErrors' => 0,
                    'serverErrors' => 0,
                    'mobile' => 0,
                    'lastSeen' => null,
                    'durations' => [],
                ];

                ++$buckets[$key]['views'];
                ++$views;
                $buckets[$key]['durations'][] = self::float($entry['duration'] ?? null);

                if ($status >= 500) {
                    ++$buckets[$key]['serverErrors'];
                    ++$serverErrors;
                } elseif (422 === $status) {
                    // A redisplayed form. Symfony's AbstractController::render()
                    // sets 422 by itself when a submitted form among the
                    // parameters is invalid, so this is a measurement rather
                    // than an inference — see ADR 0021.
                    //
                    // Above the >= 400 branch deliberately, not below it: a
                    // form sent back for correction is friction, not an error,
                    // and the two tiles on /usage say different things. Move
                    // this under >= 400 and `rejected` silently becomes a
                    // constant zero while `clientErrors` absorbs every
                    // rejection.
                    ++$buckets[$key]['rejected'];
                    ++$rejected;
                } elseif ($status >= 400) {
                    ++$buckets[$key]['clientErrors'];
                    ++$clientErrors;
                }

                if (self::isMobile($headers)) {
                    ++$buckets[$key]['mobile'];
                    ++$mobile;
                }

                $at = self::timestamp($entry['ts'] ?? null);
                if (null !== $at) {
                    $since = null === $since || $at < $since ? $at : $since;
                    $until = null === $until || $at > $until ? $at : $until;
                    if (null === $buckets[$key]['lastSeen'] || $at > $buckets[$key]['lastSeen']) {
                        $buckets[$key]['lastSeen'] = $at;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'exists' => true,
            'rows' => self::toRows($buckets),
            'views' => $views,
            'rejected' => $rejected,
            'clientErrors' => $clientErrors,
            'serverErrors' => $serverErrors,
            'mobile' => $mobile,
            'prefetched' => $prefetched,
            'unrouted' => $unrouted,
            'since' => $since,
            'until' => $until,
        ];
    }

    /**
     * @param array<string, array{path: string, method: string, views: int, rejected: int, clientErrors: int, serverErrors: int, mobile: int, lastSeen: ?\DateTimeImmutable, durations: list<float>}> $buckets
     *
     * @return list<UsageRow>
     */
    private static function toRows(array $buckets): array
    {
        $rows = [];

        foreach ($buckets as $bucket) {
            $durations = $bucket['durations'];
            unset($bucket['durations']);
            $bucket['p95'] = self::p95($durations);
            $rows[] = $bucket;
        }

        // Busiest first, which is the order a reader wants before touching a
        // sort header. ListPaginator::sortArray() is a stable no-op without an
        // explicit ?sort, so this survives as the default.
        usort($rows, static fn(array $a, array $b) => $b['views'] <=> $a['views']);

        return $rows;
    }

    /**
     * Nearest-rank p95. The mean hides the one slow render that actually
     * annoys someone; the max is whatever happened during a cold cache.
     *
     * ponytail: keeps every duration in memory to be exact — a few thousand
     * floats at roll_size 10MiB. Swap for fixed buckets if that ever grows.
     *
     * @param list<float> $durations
     */
    private static function p95(array $durations): float
    {
        if ([] === $durations) {
            return 0.0;
        }

        sort($durations);

        return $durations[(int) ceil(0.95 * \count($durations)) - 1];
    }

    /**
     * @param array<string, string>      $patterns
     * @param array<string, string|null> $memo
     */
    private static function match(UrlMatcher $matcher, RequestContext $context, array $patterns, array &$memo, string $method, string $uri): ?string
    {
        $key = $method . ' ' . $uri;

        if (\array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        // Caddy logs the raw request URI, query string included.
        $query = strpos($uri, '?');
        $path = false === $query ? $uri : substr($uri, 0, $query);
        $context->setMethod($method);

        try {
            $name = $matcher->match($path)['_route'] ?? null;
        } catch (RoutingException) {
            // ResourceNotFoundException (an asset, or a real 404 on an
            // unrouted path) and MethodNotAllowedException (a verb no route
            // accepts) are the same answer here: this app has no screen there.
            $name = null;
        }

        // Matching on the method matters: without it every POST would raise
        // MethodNotAllowedException, which carries no route name, and every
        // write action would vanish from the screen — losing exactly the
        // "which actions get performed" half of the question.
        $memo[$key] = \is_string($name)
            && !str_starts_with($name, '_')
            && !\in_array($name, self::NOT_A_SCREEN, true)
                ? ($patterns[$name] ?? $path)
                : null;

        return $memo[$key];
    }

    /**
     * Chromium sends Sec-Ch-Ua-Mobile; Safari and Firefox send nothing, hence
     * the User-Agent fallback. Still an approximation, and the screen says so.
     *
     * @param array<mixed> $headers
     */
    private static function isMobile(array $headers): bool
    {
        if ('?1' === self::header($headers, 'Sec-Ch-Ua-Mobile')) {
            return true;
        }

        return str_contains(self::header($headers, 'User-Agent') ?? '', 'Mobile');
    }

    /**
     * Caddy logs every header as a list of values, even single-valued ones.
     *
     * @param array<mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $values = $headers[$name] ?? null;

        return \is_array($values) ? self::str($values[0] ?? null) : null;
    }

    private static function str(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private static function float(mixed $value): float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }

    private static function timestamp(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_int($value) && !\is_float($value)) {
            return null;
        }

        return (new \DateTimeImmutable('@' . (int) $value))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    /** @return UsageReport */
    private static function emptyReport(): array
    {
        return [
            'exists' => false,
            'rows' => [],
            'views' => 0,
            'rejected' => 0,
            'clientErrors' => 0,
            'serverErrors' => 0,
            'mobile' => 0,
            'prefetched' => 0,
            'unrouted' => 0,
            'since' => null,
            'until' => null,
        ];
    }
}
