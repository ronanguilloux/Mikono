<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\Gender;

/**
 * Joins the legacy workbook's tabs into one LegacyPerson each (brainstorm 15):
 * intro-form rows merged by email, then roster and DIRECT rows attached by
 * exact normalised name. Nothing here reads the database or production's
 * lists; LegacyImporter maps and matches.
 */
final class LegacyPeopleBuilder
{
    public const string FORM = 'Volunteer Details';
    public const string DIRECT = 'DIRECT VOLUNTEERS';

    /** Intro-form column => Volunteer property, for the plain-text answers. */
    private const array FORM_TEXT = [
        8 => 'freeTimeActivities',
        9 => 'motivation',
        12 => 'accommodationAddress',
        13 => 'instagram',
        14 => 'facebook',
        15 => 'twitter',
        16 => 'tiktok',
        17 => 'linkedin',
        18 => 'skillsDescription',
        23 => 'hobbies',
        28 => 'employer',
        29 => 'favouriteFood',
        30 => 'education',
    ];

    /** What respondents typed for "nothing": a non-answer, not a value (brainstorm 15). */
    private const array NO_ANSWER = ['na', 'n a', 'none', 'nil', 'null', 'not applicable', 'nothing'];

    /** And, for an account question only, "no". */
    private const array NO_ACCOUNT = ['no', 'nope', 'dont have', 'i dont have', 'dont have one', 'i dont have one', 'no account'];

    private const array SOCIAL_COLUMNS = [13, 14, 15, 16, 17];

    private const array LABELS = [
        'email' => 'Email', 'phone' => 'Phone', 'dateOfBirth' => 'Date of birth', 'gender' => 'Gender',
        'profession' => 'Occupation', 'interests' => 'Interests', 'emergencyContacts' => 'Emergency contact',
        'experienceWithChildren' => 'Experience with children', 'freeTimeActivities' => 'Free-time activities',
        'motivation' => 'Why UCESCO', 'accommodationAddress' => 'Accommodation address', 'instagram' => 'Instagram',
        'facebook' => 'Facebook', 'twitter' => 'Twitter', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn',
        'skillsDescription' => 'Skills', 'hobbies' => 'Hobbies', 'employer' => 'Employer',
        'favouriteFood' => 'Favourite food', 'education' => 'School/university',
    ];

    /** @var array<string, LegacyPerson> */
    private array $people = [];

    /** @var array<string, list<string>> normalised name => person keys */
    private array $byName = [];

    /** @return array<string, LegacyPerson> key => person, in workbook order */
    public function build(LegacyWorkbook $workbook): array
    {
        $this->people = [];
        $this->byName = [];

        $this->readForm($workbook->sheet(self::FORM));
        foreach ($workbook->sheetNames() as $name) {
            if (1 === preg_match('/^(20\d\d) volunteers$/i', $name, $m)) {
                $this->readRoster((int) $m[1], $workbook->sheet($name));
            }
        }
        $this->readDirect($workbook->sheet(self::DIRECT));

        foreach ($this->people as $person) {
            $this->mergeStays($person);
            $this->flagNearNames($person);
        }

        return $this->people;
    }

    public static function normaliseName(string $name): string
    {
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($name);
        $ascii = false === $ascii || null === $ascii ? strtolower($name) : $ascii;

        return trim((string) preg_replace('/[^a-z]+/', ' ', $ascii));
    }

    /** @param list<array<int, string|\DateTimeImmutable|null>> $rows */
    private function readForm(array $rows): void
    {
        $byKey = [];
        foreach ($rows as $index => $row) {
            $name = self::text($row, 1);
            if (0 === $index || null === $name) {
                continue;
            }
            $email = self::text($row, 4);
            $key = null !== $email && false !== filter_var(strtolower($email), \FILTER_VALIDATE_EMAIL)
                ? 'email:' . strtolower($email)
                : 'name:' . self::normaliseName($name);
            $byKey[$key][] = ['row' => $index + 1, 'cells' => $row];
        }

        // The same full name under two emails is a re-submission, not a namesake:
        // one person, under the key of the latest entry.
        $byFullName = [];
        foreach ($byKey as $key => $entries) {
            $name = self::normaliseName((string) self::text($entries[0]['cells'], 1));
            if (str_contains($name, ' ')) {
                $byFullName[$name][] = $key;
            }
        }
        foreach ($byFullName as $keys) {
            if (count($keys) < 2) {
                continue;
            }
            usort($keys, static fn(string $a, string $b): int => max(array_map(static fn(array $e): string => self::stamp($e['cells']), $byKey[$a]))
                <=> max(array_map(static fn(array $e): string => self::stamp($e['cells']), $byKey[$b])));
            $latest = array_pop($keys);
            foreach ($keys as $key) {
                $byKey[$latest] = [...$byKey[$key], ...$byKey[$latest]];
                unset($byKey[$key]);
                $this->person($latest)->issues[] = 'form entries merged by name: ' . $key;
            }
        }

        foreach ($byKey as $key => $entries) {
            // Oldest first, so the latest answer is the one that sticks.
            usort($entries, static fn(array $a, array $b): int => self::stamp($a['cells']) <=> self::stamp($b['cells']));
            $person = $this->person($key);
            foreach ($entries as $entry) {
                $this->applyFormRow($person, $entry['row'], $entry['cells']);
            }
        }
    }

    /** @param array<int, string|\DateTimeImmutable|null> $row */
    private function applyFormRow(LegacyPerson $person, int $rowNumber, array $row): void
    {
        $person->seenIn[] = 'form r' . $rowNumber;
        $this->setName($person, (string) self::text($row, 1), true);

        $stamp = $row[0] ?? null;
        $person->legacy[] = 'Form submitted: ' . ($stamp instanceof \DateTimeImmutable ? $stamp->format('Y-m-d') : ($stamp ?? 'date unknown'));

        $email = self::text($row, 4);
        if (null !== $email) {
            false !== filter_var(strtolower($email), \FILTER_VALIDATE_EMAIL)
                ? $this->set($person, 'email', strtolower($email))
                : $person->legacy[] = 'Email as given: ' . $email;
        }
        $phone = self::text($row, 5);
        if (null !== $phone) {
            mb_strlen($phone) <= 50 ? $this->set($person, 'phone', $phone) : $person->legacy[] = 'Phone as given: ' . $phone;
        }
        $this->readDateOfBirth($person, $row[2] ?? null, 'Date of birth');
        if (null !== $country = self::text($row, 3)) {
            array_unshift($person->countries, $country);
        }

        $interests = implode("\n", array_filter([self::text($row, 6), self::text($row, 7)], static fn(?string $text): bool => null !== $text));
        if ('' !== $interests) {
            $this->set($person, 'interests', $interests);
        }
        foreach (self::FORM_TEXT as $column => $property) {
            $value = self::text($row, $column);
            if (null !== $value && in_array($column, self::SOCIAL_COLUMNS, true) && in_array(self::bare($value), self::NO_ACCOUNT, true)) {
                continue;
            }
            if (null !== $value) {
                $this->set($person, $property, $value);
            }
        }
        // A LinkedIn URL someone pasted into a stray column after the last question.
        if (null !== $stray = self::text($row, 32)) {
            isset($person->fields['linkedin']) ? $person->legacy[] = 'Extra answer: ' . $stray : $this->set($person, 'linkedin', $stray);
        }
        if (null !== $occupation = self::text($row, 20)) {
            mb_strlen($occupation) <= 255 ? $this->set($person, 'profession', $occupation) : $person->legacy[] = 'Occupation: ' . $occupation;
        }
        $children = strtolower((string) self::text($row, 19));
        if (in_array($children, ['yes', 'no'], true)) {
            $this->set($person, 'experienceWithChildren', 'yes' === $children);
        }
        if (null !== $contact = self::text($row, 24)) {
            $relationship = self::text($row, 27);
            $this->set($person, 'emergencyContacts', $contact . (null === $relationship ? '' : ' (' . $relationship . ')'));
        }
        if (null !== $gender = self::gender(self::text($row, 26))) {
            $this->set($person, 'gender', $gender);
        }

        foreach ([[21, 22, 'Start/End'], [10, 11, 'Arrival/Departure']] as [$from, $to, $label]) {
            if (null === ($row[$from] ?? null) && null === ($row[$to] ?? null)) {
                continue;
            }
            $start = self::formDate($row[$from] ?? null);
            $end = self::formDate($row[$to] ?? null);
            if (null === $start || null === $end || $end < $start) {
                $person->issues[] = sprintf('form r%d %s unusable', $rowNumber, $label);
                $person->legacy[] = sprintf('%s as given: %s – %s', $label, self::show($row[$from] ?? null), self::show($row[$to] ?? null));
                continue;
            }
            $person->stays[] = ['start' => $start, 'end' => $end, 'branch' => null, 'origin' => 'form r' . $rowNumber];
            break;
        }
    }

    /** @param list<array<int, string|\DateTimeImmutable|null>> $rows */
    private function readRoster(int $year, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $name = self::text($row, 0);
            if (null === $name || in_array(strtoupper($name), ['NAME', 'FULL NAME'], true) || 'BRANCH' === self::text($row, 1)) {
                continue;
            }
            $person = $this->attach($name, sprintf('%d r%d', $year, $index + 1));
            $parts = ['branch' => 1, 'project' => 2, 'activity' => 3, 'source' => 5, 'duration' => 6, 'country' => 7, 'birthday' => 8];
            $line = [];
            foreach ($parts as $label => $column) {
                if (null !== $value = self::text($row, $column)) {
                    $line[] = $label . ': ' . $value;
                }
            }
            $person->legacy[] = sprintf('%d roster', $year) . ([] === $line ? '' : ' — ' . implode('; ', $line));

            if (null !== $activity = self::text($row, 3)) {
                $person->activities[] = $activity;
            }
            if (null !== $source = self::text($row, 5)) {
                $person->sources[] = $source;
            }
            if (null !== $country = self::text($row, 7)) {
                $person->countries[] = $country;
            }
            $this->readDateOfBirth($person, $row[8] ?? null, null);
            $person->rosterBranches[$year] ??= self::text($row, 1);
            if (null !== $duration = self::text($row, 6)) {
                $person->rosterDurations[] = sprintf('%d roster: %s', $year, $duration);
            }
        }
    }

    /** @param list<array<int, string|\DateTimeImmutable|null>> $rows */
    private function readDirect(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $name = self::text($row, 0);
            if (0 === $index || null === $name) {
                continue;
            }
            $person = $this->attach($name, 'direct r' . ($index + 1));
            $labels = [1 => 'gender', 2 => 'country', 3 => 'interest', 4 => 'duration', 5 => 'start', 6 => 'end', 7 => 'status', 8 => 'branch'];
            $line = [];
            foreach ($labels as $column => $label) {
                if (null !== ($row[$column] ?? null)) {
                    $line[] = $label . ': ' . self::show($row[$column]);
                }
            }
            $person->legacy[] = 'Direct volunteers — ' . implode('; ', $line);

            if (null !== $gender = self::gender(self::text($row, 1))) {
                $this->set($person, 'gender', $gender, false);
            } elseif (null !== self::text($row, 1)) {
                $person->issues[] = 'gender unclear in DIRECT';
            }
            if (null !== $country = self::text($row, 2)) {
                $person->countries[] = $country;
            }
            if (null !== $interest = self::text($row, 3)) {
                $current = $person->fields['interests'] ?? null;
                $person->fields['interests'] = is_string($current) && !str_contains($current, $interest) ? $current . "\n" . $interest : $interest;
            }
            $duration = self::text($row, 4);
            [$start, $end, $note] = self::directDates($row[5] ?? null, $row[6] ?? null, $duration);
            if (null !== $note) {
                $person->issues[] = 'direct r' . ($index + 1) . ' ' . $note;
            }
            if (null !== $start && null !== $end) {
                $person->stays[] = ['start' => $start, 'end' => $end, 'branch' => self::text($row, 8), 'origin' => 'direct r' . ($index + 1)];
            } elseif (null !== $duration) {
                $person->rosterDurations[] = 'Direct volunteers: ' . $duration;
            }
            $person->directBranch ??= self::text($row, 8);
        }
    }

    /**
     * DIRECT was typed by hand, mixing real dates, day-first text and dates
     * Sheets read month-first. Each date cell is tried as stored and swapped;
     * the reading that ends on or after its start and is closest to the stated
     * duration wins. Returns [start, end, note-for-the-reviewer].
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable, 2: ?string}
     */
    private static function directDates(string|\DateTimeImmutable|null $from, string|\DateTimeImmutable|null $to, ?string $duration): array
    {
        $readings = static function (string|\DateTimeImmutable|null $cell, ?int $year): array {
            if ($cell instanceof \DateTimeImmutable) {
                return array_filter(['as stored' => $cell, 'swapped' => LegacyDates::swapDayMonth($cell)]);
            }

            return null === $cell ? [] : array_filter(['text' => LegacyDates::parseDayFirst($cell, $year)]);
        };
        $startReadings = $readings($from, null);
        $year = [] === $startReadings ? null : (int) reset($startReadings)->format('Y');
        $starts = [] === $startReadings ? $readings($from, $year ?? (int) date('Y')) : $startReadings;
        $ends = $readings($to, $year ?? (int) date('Y'));
        $days = 1 === preg_match('/(\d+)\s*week/i', (string) $duration, $m) ? 7 * (int) $m[1] : null;

        $best = null;
        foreach ($starts as $startHow => $start) {
            foreach ($ends as $endHow => $end) {
                if ($end < $start) {
                    continue;
                }
                $score = null === $days ? 0 : abs($start->diff($end)->days - $days);
                if (null === $best || $score < $best[0]) {
                    $best = [$score, $start, $end, 'swapped' === $startHow || 'swapped' === $endHow];
                }
            }
        }
        if (null === $best) {
            return [null, null, null === $from && null === $to ? null : 'dates unusable'];
        }
        $note = $best[3] ? 'a date read day/month swapped' : null;
        if (!$from instanceof \DateTimeImmutable && null !== $from && 1 !== preg_match('/\d{4}/', $from)) {
            $note = trim(($note ?? '') . ' year assumed ' . $best[1]->format('Y'));
        }

        return [$best[1], $best[2], $note];
    }

    private function attach(string $name, string $seenIn): LegacyPerson
    {
        $normalised = self::normaliseName($name);
        $keys = $this->byName[$normalised] ?? [];
        if (1 === count($keys)) {
            $person = $this->people[$keys[0]];
        } else {
            $person = $this->person('name:' . $normalised);
            if (count($keys) > 1) {
                $person->issues[] = 'name matches several form entries: ' . implode(', ', $keys);
            }
        }
        $person->seenIn[] = $seenIn;
        $this->setName($person, $name, false);

        return $person;
    }

    private function person(string $key): LegacyPerson
    {
        if (!isset($this->people[$key])) {
            $this->people[$key] = new LegacyPerson($key);
            $this->people[$key]->legacy[] = 'Legacy import key: ' . $key;
        }

        return $this->people[$key];
    }

    private function setName(LegacyPerson $person, string $name, bool $override): void
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        // A roster in capitals, or a form answer in lower case, reads as typed otherwise.
        if ($name === mb_strtoupper($name) || $name === mb_strtolower($name)) {
            $name = mb_convert_case(mb_strtolower($name), \MB_CASE_TITLE);
        }
        $normalised = self::normaliseName($name);
        if (!in_array($person->key, $this->byName[$normalised] ?? [], true)) {
            $this->byName[$normalised][] = $person->key;
        }
        if ('' !== $person->firstName && !$override) {
            return;
        }
        $parts = explode(' ', $name);
        $person->lastName = count($parts) > 1 ? array_pop($parts) : null;
        $person->firstName = implode(' ', $parts);
        if (null === $person->lastName) {
            $person->issues[] = 'one-word name';
        }
    }

    /** Latest answer wins; a different earlier one is kept in legacyRecord. */
    private function set(LegacyPerson $person, string $property, string|bool|\DateTimeImmutable|Gender $value, bool $override = true): void
    {
        $current = $person->fields[$property] ?? null;
        if (null !== $current && self::show($current) !== self::show($value)) {
            if (!$override) {
                return;
            }
            $person->legacy[] = sprintf('Earlier answer — %s: %s', self::LABELS[$property] ?? $property, self::show($current));
        }
        $person->fields[$property] = $value;
    }

    private function readDateOfBirth(LegacyPerson $person, string|\DateTimeImmutable|null $cell, ?string $label): void
    {
        if (null === $cell) {
            return;
        }
        if ($cell instanceof \DateTimeImmutable) {
            // Typed text Sheets parsed month-first: unambiguous only when the day is over 12.
            if ((int) $cell->format('j') <= 12 && $cell->format('j') !== $cell->format('n')) {
                $person->legacy[] = 'Date of birth: day and month may be swapped';
            }
            $date = $cell;
        } else {
            // "15.11.1997 M": some answered the gender question in this one too.
            $date = LegacyDates::parseDayFirst((string) preg_replace('/\s+[mf]$/i', '', $cell));
        }
        $plausible = null !== $date && $date < new \DateTimeImmutable('today') && $date > new \DateTimeImmutable('1920-01-01');
        if ($plausible && !isset($person->fields['dateOfBirth'])) {
            $person->fields['dateOfBirth'] = $date;
        }
        if (!$plausible && $cell instanceof \DateTimeImmutable) {
            $person->legacy[] = 'Date of birth as given: ' . $cell->format('Y-m-d');
        } elseif (!$plausible && null !== $label) {
            $person->legacy[] = $label . ' as given: ' . $cell;
        }
    }

    private function mergeStays(LegacyPerson $person): void
    {
        foreach ($person->stays as $i => $stay) {
            if (null !== $stay['branch']) {
                continue;
            }
            // A form stay's branch: the roster of its year, else the DIRECT tab.
            $year = (int) $stay['start']->format('Y');
            $person->stays[$i]['branch'] = $person->rosterBranches[$year]
                ?? $person->rosterBranches[(int) $stay['end']->format('Y')]
                ?? $person->directBranch;
        }

        usort($person->stays, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);
        $merged = [];
        foreach ($person->stays as $stay) {
            $last = array_key_last($merged);
            if (null !== $last && $stay['start'] <= $merged[$last]['end']
                && (null === $stay['branch'] || null === $merged[$last]['branch']
                    || self::normaliseName($stay['branch']) === self::normaliseName($merged[$last]['branch']))) {
                $merged[$last]['end'] = max($merged[$last]['end'], $stay['end']);
                $merged[$last]['branch'] ??= $stay['branch'];
                $merged[$last]['origin'] .= ' + ' . $stay['origin'];
                continue;
            }
            if (null !== $last && $stay['start'] <= $merged[$last]['end']) {
                $person->issues[] = 'overlapping stays at different branches';
            }
            $merged[] = $stay;
        }
        $person->stays = $merged;

        // No dates anywhere: the roster's duration is all there is (brainstorm 15, step 6).
        if ([] === $person->stays) {
            $person->notes = array_values(array_unique($person->rosterDurations));
        }
    }

    private function flagNearNames(LegacyPerson $person): void
    {
        $parts = explode(' ', self::normaliseName($person->fullName()));
        if (count($parts) < 2) {
            return;
        }
        foreach ($this->byName as $name => $keys) {
            $other = explode(' ', $name);
            if (count($other) >= 2 && $other[0] === $parts[0] && end($other) === end($parts) && !in_array($person->key, $keys, true)) {
                $person->issues[] = 'maybe the same person as ' . implode(', ', $keys);
            }
        }
    }

    private static function formDate(string|\DateTimeImmutable|null $cell): ?\DateTimeImmutable
    {
        return $cell instanceof \DateTimeImmutable ? $cell : (null === $cell ? null : LegacyDates::parseMonthFirst($cell));
    }

    private static function gender(?string $text): ?Gender
    {
        return match (strtolower((string) $text)) {
            'female' => Gender::Female,
            'male' => Gender::Male,
            default => null,
        };
    }

    /** @param array<int, string|\DateTimeImmutable|null> $row */
    private static function text(array $row, int $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        return null === $value || in_array(self::bare($value), ['', ...self::NO_ANSWER], true) ? null : $value;
    }

    /** Lower case, letters and spaces only: "N/A" is "n a", "-" is "". */
    private static function bare(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z ]/', ' ', strtolower($value))));
    }

    /** @param array<int, string|\DateTimeImmutable|null> $row */
    private static function stamp(array $row): string
    {
        return ($row[0] ?? null) instanceof \DateTimeImmutable ? $row[0]->format('c') : '';
    }

    private static function show(string|bool|\DateTimeImmutable|Gender|null $value): string
    {
        return match (true) {
            $value instanceof \DateTimeImmutable => $value->format('Y-m-d'),
            $value instanceof Gender => $value->label(),
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
    }
}
