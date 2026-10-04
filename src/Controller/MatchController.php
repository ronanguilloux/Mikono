<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Branch;
use App\Entity\Program;
use App\Entity\Skill;
use App\Enum\VolunteerStatus;
use App\Export\ListExport;
use App\Report\ProgramMatches;
use App\Report\ProgramMatchFinder;
use App\Report\VolunteerMatch;
use App\Repository\BranchRepository;
use App\Repository\ProgramRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Open programs and the volunteers who could take part, for planning the
 * next activities. Unpaginated and without sort links: the order is the
 * answer. See ADR 0042.
 */
#[Route('/matches', name: 'match_')]
final class MatchController extends AbstractController
{
    /** @var list<array{key: string, label: string}> one table per program */
    private const array COLUMNS = [
        ['key' => 'name', 'label' => 'Volunteer'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'stay', 'label' => 'Stay'],
        ['key' => 'matched', 'label' => 'Matched'],
        ['key' => 'matchedSkills', 'label' => 'Matching skills'],
        ['key' => 'missingSkills', 'label' => 'Missing skills'],
        ['key' => 'experience', 'label' => 'Experience'],
    ];

    /** @var list<array{key: string, label: string}> one row per program and volunteer */
    private const array EXPORT_COLUMNS = [
        ['key' => 'branch', 'label' => 'Branch'],
        ['key' => 'project', 'label' => 'Project'],
        ['key' => 'program', 'label' => 'Program'],
        ['key' => 'name', 'label' => 'Volunteer'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'stay', 'label' => 'Stay'],
        ['key' => 'matched', 'label' => 'Matched'],
        ['key' => 'basis', 'label' => 'Basis'],
        ['key' => 'matchedSkills', 'label' => 'Matching skills'],
        ['key' => 'missingSkills', 'label' => 'Missing skills'],
        ['key' => 'experience', 'label' => 'Experience'],
    ];

    public function __construct(
        private readonly ProgramMatchFinder $finder,
        private readonly ProgramRepository $programs,
        private readonly BranchRepository $branches,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');
        $filters = $this->requestedFilters($request);

        $groups = [];
        $noSkills = [];
        $oneSkill = [];
        foreach ($this->finder->find($today, ...$filters) as $matches) {
            $skills = $matches->program->getSkills()->count();
            if (1 === $skills) {
                $oneSkill[] = $matches->program;
            }
            if (0 === $skills) {
                $noSkills[] = $matches;
                // Nothing to show and nothing to look for: only in the list below.
                if ([] === $matches->candidates) {
                    continue;
                }
            }
            $groups[] = ['matches' => $matches, 'rows' => $this->rows($matches, $today)];
        }

        return $this->render('match/index.html.twig', [
            'columns' => self::COLUMNS,
            'groups' => $groups,
            'noSkills' => $noSkills,
            'oneSkill' => $oneSkill,
            // Picking one program narrows the list to its branch.
            'volunteersWithoutSkills' => $this->finder->findStaysWithoutSkills($today, $filters['branch'] ?? $filters['program']?->getProject()?->getBranch(), $filters['who']),
            'today' => $today,
            'filters' => $filters,
            'branchOptions' => $this->branches->findBy(['isActive' => true], ['name' => 'ASC']),
            'programOptions' => $this->programs->findOpenForMatching($today, $filters['branch']),
            'whoOptions' => [VolunteerStatus::Present, VolunteerStatus::Upcoming],
        ]);
    }

    #[Route('/export.{format}', name: 'export', requirements: ['format' => 'csv|xlsx'], defaults: ['format' => 'csv'], methods: ['GET'])]
    public function export(Request $request, string $format): StreamedResponse
    {
        $today = new \DateTimeImmutable('today');
        $rows = [];
        foreach ($this->finder->find($today, ...$this->requestedFilters($request)) as $matches) {
            $program = $matches->program;
            foreach ($matches->candidates as $match) {
                $rows[] = [
                    'branch' => $program->getProject()?->getBranch()?->getName() ?? '',
                    'project' => $program->getProject()?->getName() ?? '',
                    'program' => $program->getName(),
                    'basis' => match (true) {
                        $match->isByExperienceOnly() => 'Experience',
                        [] === $match->experience => 'Skills',
                        default => 'Skills + experience',
                    },
                ] + $this->cells($matches, $match, $today);
            }
        }

        return ListExport::response('matches', $format, self::EXPORT_COLUMNS, $rows);
    }

    /**
     * Read the ADR 0023 way: anything malformed, blank or unknown is null,
     * meaning no filter. Keyed by ProgramMatchFinder::find()'s argument names.
     *
     * @return array{branch: ?Branch, program: ?Program, who: ?VolunteerStatus}
     */
    private function requestedFilters(Request $request): array
    {
        $query = $request->query->all();
        $who = is_scalar($query['who'] ?? null) ? VolunteerStatus::tryFrom((string) $query['who']) : null;

        return [
            'branch' => $this->branches->find($this->requestedId($query, 'branch') ?? 0),
            'program' => $this->programs->find($this->requestedId($query, 'program') ?? 0),
            'who' => in_array($who, [VolunteerStatus::Present, VolunteerStatus::Upcoming], true) ? $who : null,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function requestedId(array $query, string $key): ?int
    {
        $raw = $query[$key] ?? null;

        return is_scalar($raw) && (int) $raw >= 1 ? (int) $raw : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(ProgramMatches $matches, \DateTimeImmutable $today): array
    {
        $rows = [];
        foreach ($matches->candidates as $match) {
            $id = $match->volunteer->getId();
            $row = [
                'cells' => $this->cells($matches, $match, $today),
                'links' => ['name' => $this->generateUrl('volunteer_show', ['id' => $id])],
                'pills' => ['status' => ($match->present ? VolunteerStatus::Present : VolunteerStatus::Upcoming)->tone()],
                'actions' => [['label' => 'Assign', 'url' => $this->generateUrl('activity_new', [
                    'program' => $matches->program->getId(),
                    'volunteer' => $id,
                    'date' => self::firstDay($matches, $match, $today)->format('Y-m-d'),
                ]), 'primary' => true]],
            ];
            if ($match->isByExperienceOnly()) {
                $row['badges'] = ['matched' => 'By experience'];
                if ([] !== $match->missingSkills) {
                    // Ticking the skill there makes this a skill match next time.
                    $row['badges']['missingSkills'] = 'Not on profile';
                    $row['links']['missingSkills'] = $this->generateUrl('volunteer_edit', ['id' => $id]);
                }
            } elseif ([] === $match->experience) {
                $row['badges'] = ['experience' => 'New to it'];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function cells(ProgramMatches $matches, VolunteerMatch $match, \DateTimeImmutable $today): array
    {
        $needed = $matches->program->getSkills()->count();

        return [
            'name' => $match->volunteer->getFullName(),
            'status' => ($match->present ? VolunteerStatus::Present : VolunteerStatus::Upcoming)->label(),
            'stay' => self::stay($match, $today),
            // Empty, not a dash, when there's nothing to count: the badge says why the row is there.
            'matched' => 0 === $needed ? '' : sprintf('%d of %d', count($match->matchedSkills), $needed),
            'matchedSkills' => self::skillNames($match->matchedSkills),
            'missingSkills' => self::skillNames($match->missingSkills),
            'experience' => self::experience($match),
        ];
    }

    private static function stay(VolunteerMatch $match, \DateTimeImmutable $today): string
    {
        $start = $match->stay->getStartDate();
        $end = $match->stay->getEndDate();
        if (null === $start || null === $end) {
            return '—';
        }
        if (!$match->present) {
            return $start->format('j M Y') . ' – ' . $end->format('j M Y');
        }

        $left = $today->diff($end)->days;

        return 'Until ' . $end->format('j M Y') . ' · ' . match ($left) {
            0 => 'last day',
            1 => '1 day left',
            default => $left . ' days left',
        };
    }

    /**
     * The Assign link's date: the first day from today that both the stay and
     * the program cover. The finder keeps only not-ended stays overlapping an
     * open program, so this day exists; today would refuse an upcoming
     * volunteer or a program not started yet.
     */
    private static function firstDay(ProgramMatches $matches, VolunteerMatch $match, \DateTimeImmutable $today): \DateTimeImmutable
    {
        return max(array_filter([$today, $match->stay->getStartDate(), $matches->program->getStartDate()]));
    }

    private static function experience(VolunteerMatch $match): string
    {
        if (null === $match->lastExperience) {
            return '';
        }

        $done = [];
        foreach ($match->experience as $type => $times) {
            $done[] = $type . ' ×' . $times;
        }

        return implode(', ', $done)
            . ($match->activitiesInProgram > 0 ? ' · ' . $match->activitiesInProgram . ' in this program' : '')
            . ' · last ' . $match->lastExperience->format('j M Y');
    }

    /**
     * @param list<Skill> $skills already ordered by name by ProgramMatchFinder
     */
    private static function skillNames(array $skills): string
    {
        return [] === $skills ? '—' : implode(', ', array_map(static fn(Skill $skill): string => $skill->getName(), $skills));
    }
}
