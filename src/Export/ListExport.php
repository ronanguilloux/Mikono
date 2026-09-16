<?php

declare(strict_types=1);

namespace App\Export;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes a list view's rows to a CSV or .xlsx download. The caller hands over
 * the same COLUMNS and cells() its index renders, so the file and the screen
 * cannot drift apart. See ADR 0029.
 */
final class ListExport
{
    public const string CSV = 'csv';
    public const string XLSX = 'xlsx';

    private const array CONTENT_TYPES = [
        self::CSV => 'text/csv; charset=UTF-8',
        self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * @param string                                  $name    the list's name, first part of the filename
     * @param string                                  $format  self::CSV or self::XLSX — the route requirement guarantees one of them
     * @param list<array{key: string, label: string}> $columns
     * @param iterable<array<string, string>>         $rows    each a row's `cells`, keyed by column key
     */
    public static function response(string $name, string $format, array $columns, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($format, $columns, $rows): void {
            // The CSV writer adds a UTF-8 BOM by default, so Excel reads
            // accented names correctly.
            $writer = self::XLSX === $format ? new XlsxWriter() : new CsvWriter();
            $writer->openToFile('php://output');
            $writer->addRow(self::row(array_column($columns, 'label'), false));

            foreach ($rows as $cells) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $cells[$column['key']] ?? '';
                }
                $writer->addRow(self::row($values, self::CSV === $format));
            }

            $writer->close();
        });

        $response->headers->set('Content-Type', self::CONTENT_TYPES[$format] ?? self::CONTENT_TYPES[self::CSV]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            \sprintf('%s-%s.%s', $name, new \DateTimeImmutable('today')->format('Y-m-d'), $format),
        ));

        return $response;
    }

    /**
     * @param list<string> $values
     */
    private static function row(array $values, bool $guardFormulas): Row
    {
        return new Row(array_map(
            // Names and notes are typed in by users, and Excel runs a CSV
            // cell starting with one of these as a formula. An .xlsx string
            // cell is never evaluated, so it is left alone.
            static fn(string $value): StringCell => new StringCell(
                $guardFormulas && '' !== $value && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value,
            ),
            $values,
        ));
    }
}
