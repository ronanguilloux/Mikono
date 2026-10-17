<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The legacy workbook's date dialects (brainstorm 15). Every result is a
 * calendar day at midnight, Nairobi (ADR 0024), or null when the text isn't
 * a whole date — the caller keeps the raw text in legacyRecord.
 */
final class LegacyDates
{
    private const array MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /**
     * Google Sheets read text typed day-first ("1/9/2025") month-first and
     * stored the wrong date. Null when the swap isn't a date (day > 12).
     */
    public static function swapDayMonth(\DateTimeImmutable $date): ?\DateTimeImmutable
    {
        return self::make((int) $date->format('Y'), (int) $date->format('j'), (int) $date->format('n'));
    }

    /**
     * Text typed by a person: "15.11.1997", "27/04/1998", "1998-04-27",
     * "Oct 26,1976", "13th of August", "July 14th". Day before month when
     * numeric. A missing year takes $defaultYear, or fails without one.
     */
    public static function parseDayFirst(string $text, ?int $defaultYear = null): ?\DateTimeImmutable
    {
        $text = strtolower(trim($text));
        if (1 === preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
            return self::make((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (1 === preg_match('#^(\d{1,2})\s*[/.\-]\s*(\d{1,2})\s*[/.\-]\s*(\d{2}|\d{4})$#', $text, $m)) {
            return self::make(self::year($m[3]), (int) $m[2], (int) $m[1]);
        }
        if (1 === preg_match('/^(\d{1,2})(?:st|nd|rd|th)?(?:\s+of)?\s+([a-z]{3,})\.?,?\s*(\d{2}|\d{4})?$/', $text, $m)) {
            return self::named($m[2], (int) $m[1], $m[3] ?? null, $defaultYear);
        }
        if (1 === preg_match('/^([a-z]{3,})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(\d{2}|\d{4})?$/', $text, $m)) {
            return self::named($m[1], (int) $m[2], $m[3] ?? null, $defaultYear);
        }

        return null;
    }

    /**
     * Text in a Google Form date column, month first ("8/24/0024"). A
     * two-digit or 00YY year is read as 20YY — the form's own typo.
     */
    public static function parseMonthFirst(string $text): ?\DateTimeImmutable
    {
        if (1 !== preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', trim($text), $m)) {
            return null;
        }

        return self::make(self::year($m[3]), (int) $m[1], (int) $m[2]);
    }

    private static function named(string $month, int $day, ?string $year, ?int $defaultYear): ?\DateTimeImmutable
    {
        $number = self::MONTHS[substr($month, 0, 3)] ?? null;
        $year = null !== $year && '' !== $year ? self::year($year) : $defaultYear;

        return null === $number || null === $year ? null : self::make($year, $number, $day);
    }

    private static function year(string $digits): int
    {
        $year = (int) $digits;
        if ($year >= 100) {
            return $year < 1000 ? 2000 + $year % 100 : $year;
        }

        return $year <= (int) date('y') + 1 ? 2000 + $year : 1900 + $year;
    }

    private static function make(int $year, int $month, int $day): ?\DateTimeImmutable
    {
        if ($year < 1900 || !checkdate($month, $day, $year)) {
            return null;
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
