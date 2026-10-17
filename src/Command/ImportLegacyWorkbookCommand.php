<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\LegacyAck;
use App\Import\LegacyImporter;
use App\Import\LegacyPeopleBuilder;
use App\Import\LegacyWorkbook;
use App\Repository\ActivityTypeRepository;
use App\Repository\ProgramRepository;
use App\Repository\UserRepository;
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

    private const array ACK_COLUMNS = ['key', 'action', 'volunteer', 'ack_name', 'candidates', 'proposal', 'start', 'end', 'days', 'issues'];

    public function __construct(
        private readonly LegacyImporter $importer,
        private readonly LegacyAck $ack,
        private readonly ProgramRepository $programs,
        private readonly ActivityTypeRepository $activityTypes,
        private readonly UserRepository $users,
    ) {
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
            ->addOption('with-exceptions', null, InputOption::VALUE_NONE, 'With --apply: include people with no dates anywhere')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'With --apply: only these people.csv keys, comma-separated')
            ->addOption('ack-analyse', null, InputOption::VALUE_NONE, 'ACK Clinic tab: write ack.csv; change nothing')
            ->addOption('ack-apply', null, InputOption::VALUE_NONE, 'ACK Clinic tab: write the accepted runs [--dry-run]')
            ->addOption('max-gap', null, InputOption::VALUE_REQUIRED, 'ACK: working days between two attendance days that still make one stay', '5')
            ->addOption('logged-by', null, InputOption::VALUE_REQUIRED, 'ACK: email of the user the activities are logged by')
            ->addOption('ack-program', null, InputOption::VALUE_REQUIRED, 'ACK: program id (default: "Clinic support" at ACK clinic)')
            ->addOption('ack-type', null, InputOption::VALUE_REQUIRED, 'ACK: activity type name', 'Clinic support');
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

        $book = LegacyWorkbook::open($workbook);
        if ((bool) $input->getOption('ack-analyse') || (bool) $input->getOption('ack-apply')) {
            return $this->ack($input, $io, $book, $out);
        }

        $people = (new LegacyPeopleBuilder())->build($book);
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
            $only = $input->getOption('only');
            if (is_string($only)) {
                $keys = array_map(trim(...), explode(',', $only));
                $people = array_intersect_key($people, array_flip($keys));
                $decisions = array_intersect_key($decisions, array_flip($keys));
            }
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

    private function ack(InputInterface $input, SymfonyStyle $io, LegacyWorkbook $book, string $out): int
    {
        $maxGap = $input->getOption('max-gap');
        $maxGap = is_numeric($maxGap) ? (int) $maxGap : 5;
        $programId = $input->getOption('ack-program');
        $program = is_numeric($programId)
            ? $this->programs->find((int) $programId)
            : $this->programs->createQueryBuilder('p')->join('p.project', 'j')
                ->where('j.name = :project')->andWhere('p.name = :name')
                ->setParameters(new \Doctrine\Common\Collections\ArrayCollection([new \Doctrine\ORM\Query\Parameter('project', 'ACK clinic'), new \Doctrine\ORM\Query\Parameter('name', 'Clinic support')]))
                ->getQuery()->getOneOrNullResult();
        if (!$program instanceof \App\Entity\Program) {
            $io->error('No ACK program: pass --ack-program=<id>.');

            return Command::FAILURE;
        }
        $io->text(sprintf('Program #%d %s (%s), max gap %d working days.', $program->getId(), $program->getName(), $program->getProject()?->getName(), $maxGap));

        $byName = LegacyAck::days($book);
        $decisions = $this->readAckDecisions($out . '/ack.csv');
        if ((bool) $input->getOption('ack-analyse')) {
            $rows = $this->ack->plan($byName, $program, $maxGap, $decisions);
            $handle = fopen($out . '/ack.csv', 'w');
            if (false === $handle) {
                throw new \RuntimeException('Cannot write ' . $out . '/ack.csv');
            }
            fputcsv($handle, self::ACK_COLUMNS, escape: '');
            foreach ($rows as $row) {
                fputcsv($handle, array_map(static fn(string $c): string => $row[$c], self::ACK_COLUMNS), escape: '');
            }
            fclose($handle);
            $io->table(['run', 'action', 'volunteer', 'proposal', 'days'], array_map(static fn(array $r): array => [$r['key'], $r['action'], $r['volunteer'], $r['proposal'], $r['start'] . ' → ' . $r['end']], $rows));
            $io->success(sprintf('Wrote %s/ack.csv: %d runs.', $out, count($rows)));

            return Command::SUCCESS;
        }

        $type = $this->activityTypes->findOneBy(['name' => $input->getOption('ack-type')]);
        $email = $input->getOption('logged-by');
        $user = is_string($email) ? $this->users->findOneByEmail($email) : null;
        if (null === $type || null === $user) {
            $io->error('Pass a known --ack-type and --logged-by=<user email>.');

            return Command::FAILURE;
        }
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->ack->apply($byName, $decisions, $program, $type, $user, $maxGap, !$dryRun);
        $io->table(['', 'count'], array_map(null, array_keys($result['counts']), array_values($result['counts'])));
        if ([] !== $result['problems']) {
            $io->listing($result['problems']);
            $io->error(sprintf('%d problem(s): nothing was written.', count($result['problems'])));

            return Command::FAILURE;
        }
        $dryRun ? $io->note('Dry run: rolled back.') : $io->success('Committed.');

        return Command::SUCCESS;
    }

    /** @return array<string, array{volunteer: string, action: string}> */
    private function readAckDecisions(string $path): array
    {
        if (!is_file($path) || false === $handle = fopen($path, 'r')) {
            return [];
        }
        $header = fgetcsv($handle, escape: '');
        $decisions = [];
        while (is_array($header) && false !== $row = fgetcsv($handle, escape: '')) {
            $cells = array_combine(array_map(strval(...), $header), array_map(strval(...), array_pad($row, count($header), '')));
            $decisions[$cells['key'] ?? ''] = ['volunteer' => trim($cells['volunteer'] ?? ''), 'action' => trim($cells['action'] ?? '')];
        }
        fclose($handle);

        return $decisions;
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
