<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Entity\Achievement;
use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\BeneficiaryGroup;
use App\Entity\Branch;
use App\Entity\Escort;
use App\Entity\Indicator;
use App\Entity\Program;
use App\Entity\Project;
use App\Entity\Skill;
use App\Entity\Source;
use App\Entity\Stay;
use App\Entity\User;
use App\Entity\Volunteer;
use App\Entity\VolunteerPhoto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * What an AI assistant can read, worked out from the serializer groups that
 * decide it, so /connect-ai/readable can't disagree with the API. What metadata
 * can't know is written down here. See ADR 0050.
 *
 * @phpstan-type Field array{key: string, label: string, sensitive: bool}
 * @phpstan-type Box array{name: string, tone: string, fields: list<Field>, hidden: list<string>}
 */
final readonly class ApiDataMap
{
    /** A box on the drawing => its entity and name. */
    public const array BOXES = [
        'volontaire' => [Volunteer::class, 'Volunteer'],
        'photo' => [VolunteerPhoto::class, 'Photo'],
        'sejour' => [Stay::class, 'Stay'],
        'activite' => [Activity::class, 'Activity'],
        'achievement' => [Achievement::class, 'Achievement'],
        'user' => [User::class, 'User'],
        'escorte' => [Escort::class, 'Escort'],
        'branche' => [Branch::class, 'Branch'],
        'projet' => [Project::class, 'Project'],
        'programme' => [Program::class, 'Program'],
        'type' => [ActivityType::class, 'Activity type'],
        'skill' => [Skill::class, 'Skill'],
        'source' => [Source::class, 'Source'],
        'groupe' => [BeneficiaryGroup::class, 'Beneficiary group'],
        'indicator' => [Indicator::class, 'Indicator'],
    ];

    /** Sensitive personal data as ADR 0034 rule 2 names it. */
    public const array SENSITIVE_FIELDS = [Volunteer::class => ['emergencyContacts', 'gender']];

    private const array ABOUT_A_PERSON = [VolunteerPhoto::class, Stay::class, Activity::class, Achievement::class, User::class, Escort::class];

    /** Served by /api/volunteers/{id}/photo, so no group shows it. */
    private const array ALSO_READABLE = [Volunteer::class => ['photo'], VolunteerPhoto::class => ['image']];

    private const array LABELS = ['passportNumberCiphertext' => 'Passport number'];

    public function __construct(
        private ResourceNameCollectionFactoryInterface $resourceNames,
        private ResourceMetadataCollectionFactoryInterface $resources,
        private ClassMetadataFactoryInterface $serializerMetadata,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @return array<string, Box> */
    public function boxes(): array
    {
        $boxes = [];
        foreach (self::BOXES as $id => [$class, $name]) {
            $exposed = $this->exposedFields($class);
            $hidden = [];
            if ([] !== $exposed) {
                $metadata = $this->entityManager->getClassMetadata($class);
                $hidden = array_diff([...$metadata->getFieldNames(), ...$metadata->getAssociationNames()], array_keys($exposed), self::ALSO_READABLE[$class] ?? []);
            }

            $keys = [...array_values($exposed), ...self::ALSO_READABLE[$class] ?? []];
            sort($keys);

            $boxes[$id] = [
                'name' => $name,
                'tone' => isset(self::SENSITIVE_FIELDS[$class]) ? 'sensitive' : (\in_array($class, self::ABOUT_A_PERSON, true) ? 'personal' : 'organisation'),
                'fields' => array_map(static fn(string $key): array => [
                    'key' => $key,
                    'label' => self::label($key),
                    'sensitive' => \in_array($key, self::SENSITIVE_FIELDS[$class] ?? [], true),
                ], $keys),
                'hidden' => array_values(array_map(self::label(...), $hidden)),
            ];
        }

        return $boxes;
    }

    /** @return list<string> */
    public function neverReadable(): array
    {
        $never = [];
        foreach ($this->boxes() as $box) {
            foreach ($box['hidden'] as $field) {
                $never[] = \sprintf('%s: %s', $box['name'], mb_strtolower($field));
            }
        }

        $resources = iterator_to_array($this->resourceNames->create());
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $class = $metadata->getName();
            if (str_starts_with($class, 'App\\Entity\\') && !\in_array($class, $resources, true) && VolunteerPhoto::class !== $class) {
                $never[] = self::label($metadata->getReflectionClass()->getShortName());
            }
        }
        $never[] = 'OAuth tokens';

        return $never;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, string> property => the key it has in the API
     */
    private function exposedFields(string $class): array
    {
        if (!\in_array($class, iterator_to_array($this->resourceNames->create()), true)) {
            return [];
        }

        $context = $this->resources->create($class)->getOperation()->getNormalizationContext() ?? [];
        /** @var list<string> $groups */
        $groups = $context['groups'] ?? [];

        $fields = [];
        foreach ($this->serializerMetadata->getMetadataFor($class)->getAttributesMetadata() as $name => $attribute) {
            if ([] !== array_intersect($groups, $attribute->getGroups())) {
                $fields[$name] = $attribute->getSerializedName() ?? $name;
            }
        }

        return $fields;
    }

    private static function label(string $name): string
    {
        return self::LABELS[$name] ?? ucfirst(mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $name)));
    }
}
