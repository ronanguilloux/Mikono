<?php

declare(strict_types=1);

namespace App\Tests\Integration\Docs;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Api\ApiDataMap;
use App\Entity\ApiAccess;
use App\Entity\LoginAttempt;
use App\Entity\UsageEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Stops the data maps drifting: /connect-ai/readable is generated, and /datamap is
 * written by hand, so a structural change fails here until someone has looked.
 * It proves they were stopped, not that they reviewed the wording.
 */
final class DataMapCoverageTest extends KernelTestCase
{
    private const string SNAPSHOT = __DIR__ . '/../../Fixtures/data-structure.snapshot.txt';
    private const string INSTRUCTION = 'The data structure changed: update the text in /datamap (templates/datamap/index.html.twig) if needed, then run `make data_map_snapshot`.';

    /** Staff and usage records, which are no part of how the data fits together. */
    private const array NOT_ON_MAP = [ApiAccess::class, LoginAttempt::class, UsageEvent::class];

    #[Test]
    public function everyEntityIsOnTheMapOrKnowinglyLeftOff(): void
    {
        $onMap = array_column(ApiDataMap::BOXES, 0);

        foreach (self::entities() as $metadata) {
            self::assertContains(
                $metadata->getName(),
                [...$onMap, ...self::NOT_ON_MAP],
                \sprintf('%s is new: add a box in templates/datamap/_map.html.twig, a chip and text in /datamap, and an ApiDataMap::BOXES entry, or list it in NOT_ON_MAP.', $metadata->getName()),
            );
        }
    }

    #[Test]
    public function everyBoxIsDrawnAndDescribed(): void
    {
        $drawing = (string) file_get_contents(self::templates() . '/_map.html.twig');
        $page = (string) file_get_contents(self::templates() . '/index.html.twig');

        foreach (array_keys(ApiDataMap::BOXES) as $id) {
            self::assertStringContainsString(\sprintf('data-id="%s"', $id), $drawing, $id . ' has no box on the drawing.');
            self::assertStringContainsString(\sprintf('data-pick="%s"', $id), $page, $id . ' has no chip on /datamap.');
            self::assertMatchesRegularExpression(\sprintf('/^\s+%s:\{name:/m', $id), $page, $id . ' has no text on /datamap.');
        }
    }

    #[Test]
    public function everyApiResourceHasABox(): void
    {
        self::bootKernel();
        $onMap = array_column(ApiDataMap::BOXES, 0);

        foreach (self::getContainer()->get(ResourceNameCollectionFactoryInterface::class)->create() as $resource) {
            // API Platform lists its own error resources here too.
            if (str_starts_with($resource, 'App\\')) {
                self::assertContains($resource, $onMap, $resource . ' is readable by assistants but missing from the /connect-ai map.');
            }
        }
    }

    #[Test]
    public function everySensitiveFieldStillExists(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        foreach (ApiDataMap::SENSITIVE_FIELDS as $class => $fields) {
            foreach ($fields as $field) {
                self::assertTrue($entityManager->getClassMetadata($class)->hasField($field), $field . ' was renamed: update ApiDataMap::SENSITIVE_FIELDS.');
            }
        }
    }

    #[Test]
    public function theDataStructureMatchesItsSnapshot(): void
    {
        $structure = self::structure();

        if (false !== getenv('UPDATE_DATA_MAP_SNAPSHOT')) {
            file_put_contents(self::SNAPSHOT, $structure);
        }

        self::assertFileExists(self::SNAPSHOT, self::INSTRUCTION);
        self::assertSame((string) file_get_contents(self::SNAPSHOT), $structure, self::INSTRUCTION);
    }

    private static function templates(): string
    {
        return \dirname(__DIR__, 3) . '/templates/datamap';
    }

    /** @return list<ClassMetadata<object>> */
    private static function entities(): array
    {
        self::bootKernel();
        $all = self::getContainer()->get(EntityManagerInterface::class)->getMetadataFactory()->getAllMetadata();

        return array_values(array_filter($all, static fn(ClassMetadata $metadata): bool => str_starts_with($metadata->getName(), 'App\\Entity\\')));
    }

    private static function structure(): string
    {
        $lines = [];
        foreach (self::entities() as $metadata) {
            $fields = $metadata->getFieldNames();
            $relations = $metadata->getAssociationNames();
            sort($fields);
            sort($relations);
            $lines[] = \sprintf('%s: %s | %s', $metadata->getReflectionClass()->getShortName(), implode(', ', $fields), implode(', ', $relations));
        }
        sort($lines);

        return implode("\n", $lines) . "\n";
    }
}
