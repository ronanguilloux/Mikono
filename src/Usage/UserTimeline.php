<?php

declare(strict_types=1);

namespace App\Usage;

use App\Entity\Achievement;
use App\Entity\Activity;
use App\Entity\Authored;
use App\Entity\LoginAttempt;
use App\Entity\Program;
use App\Entity\Project;
use App\Entity\Stay;
use App\Entity\User;
use App\Entity\Volunteer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Everything the database can attribute to one user, newest first, for
 * their page under /users: sign-in attempts (ADR 0028), activities they
 * logged, and records they added or last edited (ADR 0043). Not their page
 * views — the access log carries no identity (ADR 0021) — and not
 * deletions, which nothing records.
 *
 * @phpstan-type TimelineEntry array{at: \DateTimeImmutable, event: string, details: string, url: ?string, failed: bool}
 */
final readonly class UserTimeline
{
    // ponytail: newest 50 across every source, no pagination; feed the full
    // merge to ListPaginator::paginateArray if admins need further back.
    public const int LIMIT = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urls,
    ) {}

    /**
     * @return list<TimelineEntry>
     */
    public function build(User $user): array
    {
        // Matched like LoginAttemptRepository::summarize(): the address is
        // stored as typed. Attempts under an earlier address don't show.
        $entries = $this->entries(LoginAttempt::class, 'LOWER(e.identifier) = LOWER(:who)', $user->getEmail(), 'occurredAt', 'Signed in');
        // loggedBy, not createdBy: Activity has no createdBy (ADR 0043).
        array_push($entries, ...$this->entries(Activity::class, 'e.loggedBy = :who', $user, 'createdAt', 'Logged'));

        // Every Authored entity, from the metadata, as in
        // UserRepository::detachAuthorship().
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $class = $metadata->getName();
            if (!is_subclass_of($class, Authored::class)) {
                continue;
            }
            if ($metadata->hasAssociation('createdBy')) {
                array_push($entries, ...$this->entries($class, 'e.createdBy = :who', $user, 'createdAt', 'Added'));
            }
            // updatedAt > createdAt: an insert stamps updatedBy too, and
            // shouldn't also read as an edit.
            array_push($entries, ...$this->entries($class, 'e.updatedBy = :who AND e.updatedAt > e.createdAt', $user, 'updatedAt', 'Edited'));
        }

        usort($entries, static fn(array $a, array $b): int => $b['at'] <=> $a['at']);

        // Labels last, so lazy loading only runs for the rows shown.
        return array_map($this->describe(...), \array_slice($entries, 0, self::LIMIT));
    }

    /**
     * The newest LIMIT rows of one source; the merged slice can't need more.
     *
     * @param class-string $class
     *
     * @return list<array{at: \DateTimeImmutable, verb: string, subject: object}>
     */
    private function entries(string $class, string $where, mixed $who, string $at, string $verb): array
    {
        /** @var list<object> $subjects */
        $subjects = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->where($where)
            ->setParameter('who', $who)
            ->orderBy('e.' . $at, 'DESC')
            ->setMaxResults(self::LIMIT)
            ->getQuery()
            ->getResult();

        $entries = [];
        foreach ($subjects as $subject) {
            /** @var \DateTimeImmutable $time */
            $time = $this->entityManager->getClassMetadata($class)->getFieldValue($subject, $at);
            $entries[] = ['at' => $time, 'verb' => $verb, 'subject' => $subject];
        }

        return $entries;
    }

    /**
     * @param array{at: \DateTimeImmutable, verb: string, subject: object} $entry
     *
     * @return TimelineEntry
     */
    private function describe(array $entry): array
    {
        $subject = $entry['subject'];

        if ($subject instanceof LoginAttempt) {
            return [
                'at' => $entry['at'],
                'event' => $subject->isSucceeded() ? 'Signed in' : 'Failed sign-in',
                'details' => 'from ' . ($subject->getIp() ?? 'an unknown address'),
                'url' => null,
                'failed' => !$subject->isSucceeded(),
            ];
        }

        // instanceof, not $subject::class: an entity first met as another
        // row's lazy association comes back as that same proxy object.
        [$kind, $details, $url] = match (true) {
            $subject instanceof Volunteer => ['volunteer', $subject->getFullName(), $this->url('volunteer_show', $subject)],
            $subject instanceof Stay => [
                'stay',
                \sprintf('%s at %s', $subject->getVolunteer()?->getFullName() ?? '?', $subject->getBranch()?->getName() ?? '?'),
                $this->url('stay_show', $subject),
            ],
            $subject instanceof Achievement => ['achievement', $subject->getTitle(), $this->url('achievement_edit', $subject)],
            $subject instanceof Project => ['project', $subject->getName(), $this->url('project_edit', $subject)],
            $subject instanceof Program => ['program', $subject->getName(), $this->url('program_edit', $subject)],
            $subject instanceof Activity => [
                'activity',
                \sprintf('%s, %s', $subject->getVolunteer()?->getFullName() ?? '?', $subject->getDate()?->format('j M Y') ?? '?'),
                $this->url('activity_edit', $subject),
            ],
            default => throw new \LogicException(\sprintf('%s is Authored but has no label here yet.', $subject::class)),
        };

        return [
            'at' => $entry['at'],
            'event' => $entry['verb'] . ' ' . $kind,
            'details' => $details,
            'url' => $url,
            'failed' => false,
        ];
    }

    private function url(string $route, Volunteer|Stay|Achievement|Project|Program|Activity $subject): string
    {
        return $this->urls->generate($route, ['id' => $subject->getId()]);
    }
}
