<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Branch;
use App\Entity\Skill;
use App\Entity\Source;
use App\Entity\Volunteer;
use App\Enum\VolunteerStatus;
use App\Repository\VolunteerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * GET /api/volunteers through the query behind /volunteers, so `status` keeps
 * meaning what VolunteerRepository::statusRank says and the list keeps its
 * present-first order. See ADR 0050.
 *
 * @implements ProviderInterface<Volunteer>
 */
final readonly class VolunteerListProvider implements ProviderInterface
{
    public function __construct(
        private VolunteerRepository $volunteers,
        private EntityManagerInterface $entityManager,
        private Pagination $pagination,
    ) {}

    /** @return Paginator<Volunteer> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Paginator
    {
        $request = $context['request'] ?? null;
        $filters = $request instanceof Request ? $request->query->all() : [];

        $query = $this->volunteers->createOrderedByNameQueryBuilder(
            $this->search($filters['search'] ?? null),
            $this->reference(Skill::class, $filters['skill'] ?? null),
            $this->status($filters['status'] ?? null),
            $this->reference(Branch::class, $filters['branch'] ?? null),
            $this->reference(Source::class, $filters['source'] ?? null),
        )->setFirstResult($this->pagination->getOffset($operation, $context))->setMaxResults($this->pagination->getLimit($operation, $context));

        return new Paginator(new DoctrinePaginator($query));
    }

    /**
     * A reference, not find(): an id that doesn't exist matches nobody, where
     * find() would return null, which means "no filter".
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function reference(string $class, mixed $id): ?object
    {
        if (null === $id) {
            return null;
        }

        return is_numeric($id)
            ? $this->entityManager->getReference($class, (int) $id)
            : throw new BadRequestHttpException('Relation filters take a numeric id.');
    }

    private function search(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return \is_scalar($value) ? (string) $value : throw new BadRequestHttpException('search must be text.');
    }

    private function status(mixed $value): ?VolunteerStatus
    {
        if (null === $value) {
            return null;
        }

        // Ignoring a misspelt status would answer with every volunteer, which
        // reads as a true result to whoever asked (ADR 0023).
        return (\is_string($value) ? VolunteerStatus::tryFrom($value) : null)
            ?? throw new BadRequestHttpException('status must be present, upcoming, past or none.');
    }
}
