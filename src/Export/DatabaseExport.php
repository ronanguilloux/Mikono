<?php

declare(strict_types=1);

namespace App\Export;

use Doctrine\DBAL\Connection;
use OpenSpout\Writer\CSV\Writer as CsvWriter;

/**
 * The whole database as one zip of per-table CSVs, header row first — a
 * readable copy for people and spreadsheets, not a restorable backup
 * (scripts/backup-db.sh is that). Admin only. See ADR 0039.
 */
final readonly class DatabaseExport
{
    private const array SKIPPED_TABLES = [
        // Doctrine's bookkeeping, not UCESCO's data.
        'doctrine_migration_versions',
        // Photo blobs: uploaded files are out of scope for this archive.
        'volunteer_photo',
    ];

    private const array DROPPED_COLUMNS = [
        // Password hashes are nobody's business outside the login form.
        'user' => ['password'],
        // Useless without the runtime key, which never travels with a copy (ADR 0033).
        'volunteer' => ['passport_number_ciphertext'],
    ];

    public function __construct(private Connection $connection) {}

    /**
     * @return string path of the written zip; the caller deletes it
     */
    public function writeZip(): string
    {
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'db-export-');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::OVERWRITE);

        $schema = $this->connection->createSchemaManager();
        $csvPaths = [];

        foreach ($schema->listTableNames() as $table) {
            if (\in_array($table, self::SKIPPED_TABLES, true)) {
                continue;
            }

            $columns = array_values(array_diff(
                array_map(static fn($column): string => $column->getName(), $schema->listTableColumns($table)),
                self::DROPPED_COLUMNS[$table] ?? [],
            ));

            $csvPaths[] = $csvPath = (string) tempnam(sys_get_temp_dir(), 'db-export-');
            $writer = new CsvWriter();
            $writer->openToFile($csvPath);
            $writer->addRow(ListExport::row($columns, false));

            $rows = $this->connection->iterateNumeric(\sprintf(
                'SELECT %s FROM %s',
                implode(', ', array_map($this->connection->quoteSingleIdentifier(...), $columns)),
                $this->connection->quoteSingleIdentifier($table),
            ));
            foreach ($rows as $row) {
                $writer->addRow(ListExport::row(
                    array_map(static fn(mixed $value): string => \is_scalar($value) ? (string) $value : '', $row),
                    true,
                ));
            }

            $writer->close();
            $zip->addFile($csvPath, $table . '.csv');
        }

        // addFile() reads the files at close(), so they go only after it.
        $zip->close();
        array_map(unlink(...), $csvPaths);

        return $zipPath;
    }
}
