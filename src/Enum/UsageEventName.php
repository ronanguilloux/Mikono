<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The complete list of client-side gestures worth recording — and, because
 * UsageEventController resolves an incoming name through tryFrom(), the
 * whitelist that stops a browser writing arbitrary strings into the database.
 *
 * Deliberately short. Almost everything worth knowing about how this app is
 * used is a server round-trip already visible in the access log: the batch
 * form and the single form are two different routes, a filter is a query
 * string, viewing a report is a GET. These four are the exceptions — things
 * that happen entirely in the browser and leave no request behind. Adding a
 * case that duplicates something /usage already counts makes the app slower
 * and the answer no better. See ADR 0021.
 */
enum UsageEventName: string
{
    case RosterRevealed = 'roster_revealed';
    case RosterCopied = 'roster_copied';
    case VolunteerSearchUsed = 'volunteer_search_used';
    case ActivityFormAbandoned = 'activity_form_abandoned';

    public function label(): string
    {
        return match ($this) {
            self::RosterRevealed => 'Roster opened',
            self::RosterCopied => 'Roster copied to clipboard',
            self::VolunteerSearchUsed => 'Volunteer search used on the batch form',
            self::ActivityFormAbandoned => 'Activity form left without saving',
        };
    }
}
