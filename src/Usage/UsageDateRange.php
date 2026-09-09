<?php

declare(strict_types=1);

namespace App\Usage;

use Symfony\Component\HttpFoundation\Request;

/**
 * The window /usage reports on: the single place `range`, `from` and `to` are
 * read off the query string, the way ListPaginator owns `page`/`perPage`/
 * `sort`/`direction`.
 *
 * Both halves of the screen take this object — the access log AND the
 * usage_event table — or the two tables would describe different periods.
 *
 * Bad input never 400s or 404s, same promise as ListPaginator: an unknown
 * `range`, an unparseable date or a non-scalar `?range[]=x` all land on the
 * default preset. Note "the default", not "unbounded": the screen opens
 * filtered, so degrading to the whole file would silently show a reader more
 * than the control says they are looking at.
 */
final readonly class UsageDateRange
{
    /**
     * Preset key => label. The map IS the whitelist, so nothing a reader types
     * reaches a date calculation — same idiom as the controllers' SORT_MAP.
     *
     * 'all' is a listed preset rather than the absence of one: with a filtered
     * default, an unbounded view has to be reachable from the control itself.
     */
    public const array PRESETS = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'ytd' => 'Year to date',
        'all' => 'All time',
    ];

    /**
     * /usage with no query string opens on the last 7 days — "did anyone use
     * the thing I shipped last week" is the question the screen exists for.
     *
     * The control marks this preset active on a bare /usage (the template
     * reads preset(), not the URL). A pre-filtered screen that doesn't say so
     * is worse than no filter at all: an empty table would read as "nobody
     * ever used this" rather than "not in the last week".
     */
    public const string DEFAULT_PRESET = '7d';

    /** Inclusive upper bound turned exclusive once, here rather than in each caller. */
    private ?\DateTimeImmutable $untilExclusive;

    /**
     * @param ?\DateTimeImmutable $from   midnight of the first day, inclusive
     * @param ?\DateTimeImmutable $to     midnight of the last day, inclusive
     * @param ?string             $preset the PRESETS key this came from, or null for a custom pair
     */
    public function __construct(
        private ?\DateTimeImmutable $from = null,
        private ?\DateTimeImmutable $to = null,
        private ?string $preset = null,
    ) {
        $this->untilExclusive = $this->to?->modify('+1 day');
    }

    /**
     * An explicit from/to pair wins over `range`: it is the more specific
     * request, and it is what the custom form submits.
     *
     * $today is a parameter so the preset maths is testable without a clock
     * service — every other "today" in this app is a plain
     * new \DateTimeImmutable('today') too.
     */
    public static function fromRequest(Request $request, ?\DateTimeImmutable $today = null): self
    {
        $today ??= new \DateTimeImmutable('today');

        // Read through query->all() rather than InputBag::get(), which throws
        // a BadRequestException on a non-scalar: `?from[]=x` would be a 400
        // instead of the harmless fallback this class promises. Same rule as
        // ListPaginator and ActivityController::requestedDate().
        $query = $request->query->all();
        $from = self::date($query['from'] ?? null);
        $to = self::date($query['to'] ?? null);

        if (null !== $from || null !== $to) {
            // A reversed pair is a reader who filled the boxes in the order
            // that made sense to them, not an error worth a screen.
            if (null !== $from && null !== $to && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            return new self($from, $to);
        }

        $requested = $query['range'] ?? null;
        $preset = \is_string($requested) && \array_key_exists($requested, self::PRESETS)
            ? $requested
            : self::DEFAULT_PRESET;

        return self::fromPreset($preset, $today);
    }

    public static function fromPreset(string $preset, ?\DateTimeImmutable $today = null): self
    {
        $today ??= new \DateTimeImmutable('today');

        return match ($preset) {
            // Last 7 days INCLUDING today, hence -6: seven dates on a
            // calendar, which is what the label promises.
            '7d' => new self($today->modify('-6 days'), $today, $preset),
            '30d' => new self($today->modify('-29 days'), $today, $preset),
            '90d' => new self($today->modify('-89 days'), $today, $preset),
            'ytd' => new self($today->modify('first day of January'), $today, $preset),
            default => new self(null, null, 'all'),
        };
    }

    /**
     * A line the report can't place in time. True only when this range takes
     * everything: with a window, an entry whose `ts` didn't parse belongs to
     * no period, and counting it would inflate whichever one is on screen.
     */
    public function contains(?\DateTimeImmutable $at): bool
    {
        if (null === $at) {
            return !$this->isBounded();
        }

        if (null !== $this->from && $at < $this->from) {
            return false;
        }

        return null === $this->untilExclusive || $at < $this->untilExclusive;
    }

    public function isBounded(): bool
    {
        return null !== $this->from || null !== $this->to;
    }

    public function from(): ?\DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): ?\DateTimeImmutable
    {
        return $this->to;
    }

    public function untilExclusive(): ?\DateTimeImmutable
    {
        return $this->untilExclusive;
    }

    /** The active PRESETS key, or null for a custom pair — what the control ticks. */
    public function preset(): ?string
    {
        return $this->preset;
    }

    public function label(): string
    {
        if (null !== $this->preset) {
            return self::PRESETS[$this->preset];
        }

        return match (true) {
            null === $this->to => 'From ' . $this->from?->format('j M Y'),
            null === $this->from => 'Up to ' . $this->to->format('j M Y'),
            default => $this->from->format('j M Y') . ' – ' . $this->to->format('j M Y'),
        };
    }

    /**
     * Part of the /usage cache key. The resolved dates rather than the preset
     * name, so ?range=7d and the equivalent hand-typed pair share one entry —
     * and so a preset key never has to survive a rename to stay correct.
     *
     * Digits and dashes only: PSR-6 reserves {}()/\@: in a key.
     */
    public function cacheKey(): string
    {
        if (!$this->isBounded()) {
            return 'all';
        }

        return ($this->from?->format('Ymd') ?? 'start') . '-' . ($this->to?->format('Ymd') ?? 'end');
    }

    /**
     * `!` resets the time to midnight, so a range boundary is a whole day
     * rather than the moment the page was rendered.
     *
     * The round-trip comparison is what createFromFormat() alone doesn't give:
     * it accepts overflow, so `2026-13-45` comes back as a real date in
     * February 2027 rather than as false. Re-formatting catches that, and it
     * is cheaper than reading getLastErrors().
     */
    private static function date(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return false === $date || $date->format('Y-m-d') !== $raw ? null : $date;
    }
}
