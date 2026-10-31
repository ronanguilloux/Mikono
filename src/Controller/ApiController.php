<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Project;
use App\Entity\Skill;
use App\Entity\Stay;
use App\Entity\Volunteer;
use App\Enum\PeriodStep;
use App\Enum\VolunteerStatus;
use App\Report\ActivitySummaryCalculator;
use App\Report\AnniversaryReminderFinder;
use App\Report\ArrivalReminderFinder;
use App\Report\BirthdayReminderFinder;
use App\Report\DepartureReminderFinder;
use App\Report\ImpactByMonthCalculator;
use App\Report\OccupancyLookAhead;
use App\Report\PeriodTotalsCalculator;
use App\Report\ProgramMatchFinder;
use App\Report\QuietProject;
use App\Report\QuietProjectFinder;
use App\Report\ReportMetricsCalculator;
use App\Report\RosterBuilder;
use App\Report\VolunteerImpactCalculator;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The parts of the API that aren't a Doctrine resource: a volunteer's photo,
 * and every report, computed by the same src/Report/ services as the screens
 * so that the numbers can't drift apart. See ADR 0050.
 */
final readonly class ApiController
{
    /** Report => the query parameters it reads. Anything else is refused (ADR 0023). */
    private const array REPORT_PARAMETERS = [
        'metrics' => [],
        'activity-summary' => ['by', 'year'],
        'periods' => ['branch', 'step'],
        'roster' => ['date'],
        'quiet-projects' => [],
        'program-matches' => ['branch', 'program', 'who'],
        'volunteer-impact' => ['volunteer'],
        'stay-impact' => ['stay'],
        'impact-by-month' => [],
        'occupancy' => [],
        'birthdays' => [],
        'arrivals' => [],
        'departures' => [],
        'anniversaries' => [],
    ];

    /** activity-summary's `by` => the API collection its rows' ids belong to. */
    private const array SUMMARY_COLLECTIONS = [
        'volunteer' => 'volunteers',
        'project' => 'projects',
        'program' => 'programs',
        'activity_type' => 'activity_types',
        'escort' => 'escorts',
        'branch' => 'branches',
        'beneficiary_group' => 'beneficiary_groups',
        'source' => 'sources',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityRepository $activities,
        private ActivitySummaryCalculator $summaries,
        private ReportMetricsCalculator $metrics,
        private PeriodTotalsCalculator $periods,
        private RosterBuilder $rosters,
        private QuietProjectFinder $quietProjects,
        private ProgramMatchFinder $matches,
        private VolunteerImpactCalculator $impact,
        private ImpactByMonthCalculator $impactByMonth,
        private OccupancyLookAhead $occupancy,
        private BirthdayReminderFinder $birthdays,
        private ArrivalReminderFinder $arrivals,
        private DepartureReminderFinder $departures,
        private AnniversaryReminderFinder $anniversaries,
    ) {}

    #[Route('/api/volunteers/{id}/photo', name: 'api_volunteer_photo', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function photo(Volunteer $volunteer): Response
    {
        $photo = $volunteer->getPhoto() ?? throw new NotFoundHttpException('This volunteer has no photo.');

        return new Response($photo->getBytes(), 200, ['Content-Type' => 'image/jpeg']);
    }

    #[Route('/api/reports/{name}', name: 'api_report', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function report(string $name, Request $request): JsonResponse
    {
        $parameters = self::REPORT_PARAMETERS[$name] ?? throw new NotFoundHttpException(\sprintf('Unknown report "%s".', $name));
        $query = $request->query->all();
        foreach ($query as $key => $value) {
            if (!\in_array($key, $parameters, true) || !\is_scalar($value)) {
                throw new BadRequestHttpException(\sprintf('Report "%s" does not take "%s" like that. It takes: %s.', $name, $key, [] === $parameters ? 'nothing' : implode(', ', $parameters)));
            }
        }

        $today = new \DateTimeImmutable('today');

        return new JsonResponse(self::plain(match ($name) {
            'metrics' => $this->metrics->calculate($today),
            'activity-summary' => $this->summary($query),
            'periods' => $this->periods->calculate(
                $today,
                $this->entity(Branch::class, $query['branch'] ?? null),
                isset($query['step']) ? self::enum(PeriodStep::class, $query['step']) : PeriodStep::Month,
            ),
            'roster' => $this->rosters->buildFor($this->date($query['date'] ?? null) ?? $today),
            'quiet-projects' => $this->quietProjects->find($today),
            'program-matches' => $this->matches->find(
                $today,
                $this->entity(Branch::class, $query['branch'] ?? null),
                $this->entity(Program::class, $query['program'] ?? null),
                isset($query['who']) ? self::enum(VolunteerStatus::class, $query['who']) : null,
            ),
            'volunteer-impact' => $this->volunteerImpact($query, $today),
            'stay-impact' => $this->stayImpact($query, $today),
            'impact-by-month' => $this->impactByMonth->calculate($today),
            'occupancy' => $this->occupancy->calculate($today),
            'birthdays' => $this->birthdays->find($today),
            'arrivals' => $this->arrivals->find($today),
            'departures' => $this->departures->find($today),
            'anniversaries' => $this->anniversaries->find($today),
        }));
    }

    /**
     * @param array<array-key, scalar> $query
     *
     * @return list<array<string, mixed>>
     */
    private function summary(array $query): array
    {
        $by = $query['by'] ?? 'volunteer';
        $rows = match ($by) {
            'volunteer' => $this->summaries->summarizeByVolunteer(),
            'project' => $this->summaries->summarizeByProject(),
            'program' => $this->summaries->summarizeByProgram(),
            'activity_type' => $this->summaries->summarizeByActivityType(),
            'escort' => $this->summaries->summarizeByEscort(),
            'branch' => $this->summaries->summarizeByBranch(),
            'beneficiary_group' => $this->summaries->summarizeByBeneficiaryGroup(),
            'source' => $this->summaries->summarizeBySource($this->year($query['year'] ?? date('Y'))),
            default => throw new BadRequestHttpException('Unknown "by".'),
        };

        return array_map(
            static fn(array $row): array => null === $row['id'] ? $row : ['@id' => \sprintf('/api/%s/%d', self::SUMMARY_COLLECTIONS[$by], $row['id'])] + $row,
            $rows,
        );
    }

    /** @param array<array-key, scalar> $query */
    private function volunteerImpact(array $query, \DateTimeImmutable $today): object
    {
        $volunteer = $this->entity(Volunteer::class, $query['volunteer'] ?? null)
            ?? throw new BadRequestHttpException('volunteer must be a volunteer id.');

        return $this->impact->calculate($this->activities->findByVolunteerOrderedByDateDesc($volunteer), $today);
    }

    /** @param array<array-key, scalar> $query */
    private function stayImpact(array $query, \DateTimeImmutable $today): object
    {
        $stay = $this->entity(Stay::class, $query['stay'] ?? null)
            ?? throw new BadRequestHttpException('stay must be a stay id.');

        // Planned included, like the stay page: see VolunteerImpactCalculator.
        return $this->impact->calculate($this->activities->findByStayOrderedByDateDesc($stay), $today, withPlanned: true);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null null when the parameter wasn't sent. An id that matches
     *                nothing is refused: null means "no filter" to the reports,
     *                so it would answer for everyone.
     */
    private function entity(string $class, bool|float|int|string|null $id): ?object
    {
        if (null === $id) {
            return null;
        }

        return (is_numeric($id) ? $this->entityManager->find($class, (int) $id) : null)
            ?? throw new BadRequestHttpException(\sprintf('No %s has the id "%s".', new \ReflectionClass($class)->getShortName(), $id));
    }

    private function date(bool|float|int|string|null $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        // The round trip catches what the format doesn't: 02-31 parses, as March.
        if (false === $date || $date->format('Y-m-d') !== (string) $value) {
            throw new BadRequestHttpException('date must be a real day, YYYY-MM-DD.');
        }

        return $date;
    }

    private function year(bool|float|int|string $value): int
    {
        return ctype_digit((string) $value) ? (int) $value : throw new BadRequestHttpException('year must be YYYY.');
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private static function enum(string $enum, bool|float|int|string $value): \BackedEnum
    {
        return $enum::tryFrom((string) $value)
            ?? throw new BadRequestHttpException(\sprintf('"%s" is not one of: %s.', $value, implode(', ', array_map(static fn(\BackedEnum $case): string => (string) $case->value, $enum::cases()))));
    }

    /**
     * Reports return entities and value objects alike. An entity becomes its id
     * and label only, so a report can't expose a field the resources' #[Groups]
     * don't. One missing from this list falls to get_object_vars(), which sees
     * no private property from here: it comes out empty, never whole.
     */
    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof Volunteer => self::reference('volunteers', $value->getId(), $value->getFullName()),
            $value instanceof Program => self::reference('programs', $value->getId(), $value->getName()),
            $value instanceof Project => self::reference('projects', $value->getId(), $value->getName()),
            $value instanceof Branch => self::reference('branches', $value->getId(), $value->getName()),
            $value instanceof Skill => self::reference('skills', $value->getId(), $value->getName()),
            $value instanceof ActivityType => self::reference('activity_types', $value->getId(), $value->getName()),
            $value instanceof Stay => self::reference('stays', $value->getId(), null),
            $value instanceof QuietProject => self::reference('projects', $value->id, $value->name) + array_map(self::plain(...), get_object_vars($value)),
            \is_array($value) => array_map(self::plain(...), $value),
            \is_object($value) => array_map(self::plain(...), get_object_vars($value)),
            default => $value,
        };
    }

    /** @return array{'@id': string, id: ?int, label: ?string} */
    private static function reference(string $collection, ?int $id, ?string $label): array
    {
        return ['@id' => \sprintf('/api/%s/%d', $collection, $id), 'id' => $id, 'label' => $label];
    }
}
