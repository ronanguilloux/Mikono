<?php

declare(strict_types=1);

namespace App\Import;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * UCESCO's pre-Mikono volunteer Google Sheet, read whole into memory: sheet
 * name (trimmed — " 2024 Volunteers" has a leading space) => rows, each row
 * column index => trimmed string, a date, or null. Empty rows are kept, so a
 * row's index + 1 is its spreadsheet row number. See brainstorm 15.
 *
 * Date-formatted cells arrive as dates (midnight, Nairobi). A number in a
 * cell that isn't date-formatted comes back as its string ("45846"), which
 * matters for the ACK tab: see LegacyAck.
 */
final class LegacyWorkbook
{
    /** @param array<string, list<array<int, string|\DateTimeImmutable|null>>> $sheets */
    private function __construct(private readonly array $sheets) {}

    public static function open(string $path): self
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->cells as $index => $cell) {
                    $cells[$index] = self::value($cell->getValue());
                }
                $rows[] = $cells;
            }
            $sheets[trim($sheet->getName())] = $rows;
        }
        $reader->close();

        return new self($sheets);
    }

    /** @param array<string, list<array<int, string|\DateTimeImmutable|null>>> $sheets */
    public static function fromArray(array $sheets): self
    {
        return new self($sheets);
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /** @return list<array<int, string|\DateTimeImmutable|null>> */
    public function sheet(string $name): array
    {
        return $this->sheets[$name] ?? throw new \InvalidArgumentException(sprintf('No sheet "%s" in the workbook.', $name));
    }

    private static function value(mixed $value): string|\DateTimeImmutable|null
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d'));
        }
        if (is_int($value) || is_float($value)) {
            // A whole float prints without ".0", so "45846" not "45846.0".
            $value = (string) (floor($value) === $value ? (int) $value : $value);
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
