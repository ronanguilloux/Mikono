<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Enum\VolunteerStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Plain TestCase, no kernel and no database: Volunteer::getStatus() reads
 * only the stays in memory. It lives in Integration because tests/ has no
 * Unit directory. The SQL twin of this rule is covered by
 * VolunteerControllerTest's filter test. See ADR 0026.
 */
final class VolunteerStatusTest extends TestCase
{
    private const string TODAY = '2026-10-03';

    /** @param list<array{string, string}> $stays start and end, as Y-m-d */
    #[Test]
    #[DataProvider('stays')]
    public function theFirstMatchingRuleSetsTheStatus(array $stays, VolunteerStatus $expected): void
    {
        $volunteer = new Volunteer();
        foreach ($stays as [$start, $end]) {
            $volunteer->addStay((new Stay())
                ->setStartDate(new \DateTimeImmutable($start))
                ->setEndDate(new \DateTimeImmutable($end)));
        }

        self::assertSame($expected, $volunteer->getStatus(new \DateTimeImmutable(self::TODAY)));
    }

    /** @return iterable<string, array{list<array{string, string}>, VolunteerStatus}> */
    public static function stays(): iterable
    {
        yield 'a stay ending today' => [[['2026-09-01', '2026-10-03']], VolunteerStatus::Present];
        yield 'a stay starting today' => [[['2026-10-03', '2026-10-31']], VolunteerStatus::Present];
        yield 'present beats an upcoming stay' => [[['2026-09-01', '2026-10-10'], ['2026-12-01', '2026-12-31']], VolunteerStatus::Present];
        yield 'a stay starting tomorrow' => [[['2026-10-04', '2026-10-31']], VolunteerStatus::Upcoming];
        yield 'upcoming beats a past stay' => [[['2025-01-01', '2025-02-01'], ['2026-10-04', '2026-10-31']], VolunteerStatus::Upcoming];
        yield 'a stay ending yesterday' => [[['2026-09-01', '2026-10-02']], VolunteerStatus::Past];
        yield 'no stays' => [[], VolunteerStatus::NoStay];
    }
}
