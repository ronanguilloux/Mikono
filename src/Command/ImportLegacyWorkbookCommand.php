<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\LegacyImporter;
use App\Import\LegacyPeopleBuilder;
use App\Import\LegacyWorkbook;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * The one-off import of UCESCO's legacy volunteer workbook (brainstorm 15).
 * --analyse writes mapping.yaml and people.csv for a reviewer to edit;
 * --apply writes what they decided, in one transaction (--dry-run rolls it
 * back). Re-running either is safe: decisions and imported rows are found
 * again by key.
 *
 * @phpstan-import-type Mapping from LegacyImporter
 * @phpstan-import-type Decision from LegacyImporter
 */
#[AsCommand(
    name: 'app:import:legacy-workbook',
    description: 'Import the legacy volunteer workbook (.xlsx): --analyse, then --apply [--dry-run]',
)]
final class ImportLegacyWorkbookCommand extends Command
{
    private const array COLUMNS = ['key', 'action', 'branch', 'proposed', 'suggestion', 'exception', 'name', 'email', 'match', 'stays', 'seen_in', 'issues'];

    public function __construct(private readonly LegacyImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('workbook', InputArgument::REQUIRED, 'Path to the .xlsx')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Folder for mapping.yaml and people.csv (default: <workbook folder>/out)')
            ->addOption('analyse', null, InputOption::VALUE_NONE, 'Write the mapping and people files; change nothing')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the decided people to the database')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'With --apply: roll the transaction back')
            ->addOption('with-exceptions', null, InputOption::VALUE_NONE, 'With --apply: include people with no dates anywhere');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workbook = $input->getArgument('workbook');
        if (!is_string($workbook)) {
            throw new \InvalidArgumentException('Pass the path to the .xlsx.');
        }
        $outOption = $input->getOption('out');
        $out = is_string($outOption) ? $outOption : dirname($workbook) . '/out';
        if (!is_dir($out) && !mkdir($out, 0o700, true)) {
            throw new \RuntimeException('Cannot create ' . $out);
        }

        $people = (new LegacyPeopleBuilder())->build(LegacyWorkbook::open($workbook));
        $mapping = $this->importer->proposeMapping($people, $this->readMapping($out . '/mapping.yaml'));
        $decisions = $this->readDecisions($out . '/people.csv');

        if ((bool) $input->getOption('analyse')) {
            file_put_contents($out . '/mapping.yaml', Yaml::dump($mapping, 4, 2, Yaml::DUMP_NULL_AS_TILDE | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
            $rows = $this->importer->plan($people, $mapping, $decisions);
            $this->writeCsv($out . '/people.csv', $rows);

            $tally = [];
            foreach ($rows as $row) {
                $kind = ($row['exception'] ? 'exception ' : '') . preg_replace('/:\d+$/', '', $row['action']);
                $tally[$kind] = ($tally[$kind] ?? 0) + 1;
            }
            ksort($tally);
            $io->table(['action', 'people'], array_map(null, array_keys($tally), array_values($tally)));
            $io->success(sprintf('Wrote %s/mapping.yaml and %s/people.csv for %d people.', $out, $out, count($rows)));

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('apply')) {
            $dryRun = (bool) $input->getOption('dry-run');
            $result = $this->importer->apply($people, $mapping, $decisions, (bool) $input->getOption('with-exceptions'), !$dryRun);
            $io->table(['', 'count'], array_map(null, array_keys($result['counts']), array_values($result['counts'])));
            if ([] !== $result['problems']) {
                $io->listing(array_slice($result['problems'], 0, 200));
                $io->error(sprintf('%d problem(s): nothing was written.', count($result['problems'])));

                return Command::FAILURE;
            }
            $dryRun ? $io->note('Dry run: rolled back.') : $io->success('Committed.');

            return Command::SUCCESS;
        }

        $io->error('Pass --analyse or --apply.');

        return Command::INVALID;
    }

    /** @return Mapping */
    private function readMapping(string $path): array
    {
        $empty = ['branches' => [], 'sources' => [], 'skills' => [], 'countries' => []];
        if (!is_file($path)) {
            return $empty;
        }
        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed)) {
            return $empty;
        }
        $mapping = $empty;
        foreach (['branches', 'sources', 'countries'] as $name) {
            foreach (is_array($parsed[$name] ?? null) ? $parsed[$name] : [] as $raw => $value) {
                $mapping[$name][(string) $raw] = is_scalar($value) ? (string) $value : null;
            }
        }
        foreach (is_array($parsed['skills'] ?? null) ? $parsed['skills'] : [] as $raw => $value) {
            $mapping['skills'][(string) $raw] = is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
        }

        return $mapping;
    }

    /** @return array<string, Decision> */
    private function readDecisions(string $path): array
    {
        if (!is_file($path) || false === $handle = fopen($path, 'r')) {
            return [];
        }
        $header = fgetcsv($handle, escape: '');
        $decisions = [];
        while (is_array($header) && false !== $row = fgetcsv($handle, escape: '')) {
            $cells = array_combine(array_map(strval(...), $header), array_map(strval(...), array_pad($row, count($header), '')));
            $decisions[$cells['key'] ?? ''] = ['action' => trim($cells['action'] ?? ''), 'branch' => trim($cells['branch'] ?? '')];
        }
        fclose($handle);

        return $decisions;
    }

    /** @param list<array<string, string|bool>> $rows */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new \RuntimeException('Cannot write ' . $path);
        }
        fputcsv($handle, self::COLUMNS, escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn(string $c): string => is_bool($row[$c]) ? ($row[$c] ? 'yes' : '') : $row[$c], self::COLUMNS), escape: '');
        }
        fclose($handle);
    }
}
