<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\Gender;

/**
 * One person as the legacy workbook knows them, every tab joined: what
 * LegacyPeopleBuilder read, before any mapping to production's lists.
 */
final class LegacyPerson
{
    public string $firstName = '';

    public ?string $lastName = null;

    /** @var array<string, string|bool|\DateTimeImmutable|Gender> Volunteer property => value, set ones only */
    public array $fields = [];

    /** @var list<string> Raw country texts, best first: form, DIRECT, rosters */
    public array $countries = [];

    /** @var list<string> Raw roster ACTIVITY values, for the skills mapping */
    public array $activities = [];

    /** @var list<string> Raw roster SOURCE values */
    public array $sources = [];

    /** @var list<array{start: \DateTimeImmutable, end: \DateTimeImmutable, branch: ?string, origin: string}> */
    public array $stays = [];

    /** @var list<string> Lines for legacyRecord, the import key first */
    public array $legacy = [];

    /** @var list<string> Lines appended to notes (exceptions' durations) */
    public array $notes = [];

    /** @var list<string> What a reviewer should look at */
    public array $issues = [];

    /** @var list<string> Where the person was found: "form r12", "2024 r40" */
    public array $seenIn = [];

    /** @var array<int, ?string> Roster year => its raw BRANCH, for dating a form stay's branch */
    public array $rosterBranches = [];

    /** @var list<string> "2024 roster: 2 WEEKS", kept for when no stay has dates */
    public array $rosterDurations = [];

    public ?string $directBranch = null;

    public function __construct(public readonly string $key) {}

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . ($this->lastName ?? ''));
    }

    public function hasDates(): bool
    {
        return [] !== $this->stays;
    }
}
