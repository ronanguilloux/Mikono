<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Project;
use App\Entity\Stay;
use App\Entity\Volunteer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Achievement>
 */
class AchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achievement::class);
    }

    /** Newest first, with everything a row shows joined in. */
    public function createNewestFirstQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('ach')
            ->addSelect('s', 'v', 'b', 'p', 'i')
            ->join('ach.stay', 's')
            ->join('s.volunteer', 'v')
            ->join('s.branch', 'b')
            ->join('ach.project', 'p')
            ->leftJoin('ach.indicator', 'i')
            ->orderBy('ach.achievedOn', 'DESC')
            ->addOrderBy('ach.id', 'DESC');
    }

    /**
     * Achievements that count something (ADR 0047) dated from $from to $to,
     * both days included, with their indicator joined in.
     *
     * @return list<Achievement>
     */
    public function findCountedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Achievement> $achievements */
        $achievements = $this->createQueryBuilder('ach')
            ->addSelect('i')
            ->join('ach.indicator', 'i')
            ->where('ach.achievedOn BETWEEN :from AND :to')
            ->andWhere('ach.quantity IS NOT NULL')
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->getQuery()
            ->getResult();

        return $achievements;
    }

    /** @return list<Achievement> */
    public function findForVolunteer(Volunteer $volunteer): array
    {
        /** @var list<Achievement> $achievements */
        $achievements = $this->createNewestFirstQueryBuilder()
            ->where('s.volunteer = :volunteer')
            ->setParameter('volunteer', $volunteer)
            ->getQuery()
            ->getResult();

        return $achievements;
    }

    /**
     * Achievements in this stay dated outside the dates the stay carries
     * *now* — read before flushing a stay edit, like
     * StayRepository::countActivitiesOutside().
     */
    public function countOutside(Stay $stay): int
    {
        if (null === $stay->getId() || null === $stay->getStartDate() || null === $stay->getEndDate()) {
            return 0;
        }

        return (int) $this->createQueryBuilder('ach')
            ->select('COUNT(ach.id)')
            ->where('ach.stay = :stay AND (ach.achievedOn < :start OR ach.achievedOn > :end)')
            ->setParameter('stay', $stay)
            ->setParameter('start', $stay->getStartDate(), Types::DATE_IMMUTABLE)
            ->setParameter('end', $stay->getEndDate(), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Achievements in this stay at another branch's projects (ADR 0027). */
    public function countInStayAtOtherBranch(Stay $stay): int
    {
        if (null === $stay->getId() || null === $stay->getBranch()) {
            return 0;
        }

        return (int) $this->createQueryBuilder('ach')
            ->select('COUNT(ach.id)')
            ->join('ach.project', 'p')
            ->where('ach.stay = :stay AND p.branch <> :branch')
            ->setParameter('stay', $stay)
            ->setParameter('branch', $stay->getBranch())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Achievements at this project in stays at another branch (ADR 0027). */
    public function countAtProjectInOtherBranch(Project $project): int
    {
        if (null === $project->getId() || null === $project->getBranch()) {
            return 0;
        }

        return (int) $this->createQueryBuilder('ach')
            ->select('COUNT(ach.id)')
            ->join('ach.stay', 's')
            ->where('ach.project = :project AND s.branch <> :branch')
            ->setParameter('project', $project)
            ->setParameter('branch', $project->getBranch())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForProject(Project $project): int
    {
        return $this->count(['project' => $project]);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, int> project id => its achievements; absent means none
     */
    public function countForProjects(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<array{projectId: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('ach')
            ->select('IDENTITY(ach.project) AS projectId', 'COUNT(ach.id) AS total')
            ->where('ach.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->groupBy('ach.project')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['projectId']] = (int) $row['total'];
        }

        return $counts;
    }
}
