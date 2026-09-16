<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\Program;
use App\Entity\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Program>
 */
class ProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Program::class);
    }

    /**
     * Project and branch are fetch-joined: the index shows and sorts by both.
     */
    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('prg')
            ->join('prg.project', 'p')
            ->join('p.branch', 'b')
            ->addSelect('p', 'b')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('prg.name', 'ASC');
    }

    /**
     * Part of the project delete-guard: a project's programs would otherwise
     * be orphaned (ADR 0030).
     */
    public function countForProject(Project $project): int
    {
        return $this->count(['project' => $project]);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, int> project id => its programs; absent means none
     */
    public function countForProjects(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<array{projectId: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('prg')
            ->select('IDENTITY(prg.project) AS projectId', 'COUNT(prg.id) AS total')
            ->where('prg.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->groupBy('prg.project')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['projectId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Which programs offer each type, for the activity forms' type filter.
     *
     * @return array<int, list<int>> activity type id => program ids
     */
    public function findProgramIdsByActivityType(): array
    {
        /** @var list<array{programId: int|string, typeId: int|string}> $rows */
        $rows = $this->createQueryBuilder('prg')
            ->select('prg.id AS programId', 't.id AS typeId')
            ->join('prg.activityTypes', 't')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['typeId']][] = (int) $row['programId'];
        }

        return $map;
    }

    /**
     * The program delete-guard, and the reason a program can't move to
     * another project (ADR 0030).
     */
    public function countActivities(Program $program): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(a.id) FROM ' . Activity::class . ' a WHERE a.program = :program')
            ->setParameter('program', $program)
            ->getSingleScalarResult();
    }

    /**
     * @param list<Program> $programs
     *
     * @return array<int, int> program id => its activities; absent means none
     */
    public function countActivitiesFor(array $programs): array
    {
        if ([] === $programs) {
            return [];
        }

        /** @var list<array{programId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(a.program) AS programId, COUNT(a.id) AS total
                 FROM ' . Activity::class . ' a
                 WHERE a.program IN (:programs)
                 GROUP BY a.program',
            )
            ->setParameter('programs', $programs)
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['programId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Activities outside the dates the program carries *now* — read before
     * flushing an edit, so narrowing the dates can't strand them.
     */
    public function countActivitiesOutsideItsDates(Program $program): int
    {
        $start = $program->getStartDate();
        $end = $program->getEndDate();
        if (null === $program->getId() || (null === $start && null === $end)) {
            return 0;
        }

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Activity::class, 'a')
            ->where('a.program = :program')
            ->setParameter('program', $program);

        $outside = $queryBuilder->expr()->orX();
        if (null !== $start) {
            $outside->add('a.date < :start');
            $queryBuilder->setParameter('start', $start, Types::DATE_IMMUTABLE);
        }
        if (null !== $end) {
            $outside->add('a.date > :end');
            $queryBuilder->setParameter('end', $end, Types::DATE_IMMUTABLE);
        }

        return (int) $queryBuilder->andWhere($outside)->getQuery()->getSingleScalarResult();
    }

    /**
     * The types this program's activities use that it no longer offers —
     * read before flushing an edit.
     *
     * @return list<ActivityType>
     */
    public function findUsedActivityTypesNoLongerOffered(Program $program): array
    {
        if (null === $program->getId()) {
            return [];
        }

        /** @var list<ActivityType> $used */
        $used = $this->getEntityManager()
            ->createQuery('SELECT t FROM ' . ActivityType::class . ' t WHERE EXISTS (SELECT a.id FROM ' . Activity::class . ' a WHERE a.program = :program AND a.activityType = t) ORDER BY t.name ASC')
            ->setParameter('program', $program)
            ->getResult();

        return array_values(array_filter($used, static fn(ActivityType $type) => !$program->offers($type)));
    }
}
