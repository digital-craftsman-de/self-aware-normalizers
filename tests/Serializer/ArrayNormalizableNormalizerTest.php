<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\Project;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayNormalizableNormalizer::class)]
final class ArrayNormalizableNormalizerTest extends TestCase
{
    #[Test]
    public function supports_normalization_works(): void
    {
        // -- Arrange
        $normalizer = new ArrayNormalizableNormalizer();
        $project = new Project(
            projectId: ProjectId::generateRandom(),
            name: 'Great project',
        );

        // -- Act & Assert
        self::assertTrue($normalizer->supportsNormalization($project));
        self::assertFalse($normalizer->supportsNormalization(ProjectId::generateRandom()));
    }

    #[Test]
    public function supports_denormalization_works(): void
    {
        // -- Arrange
        $normalizer = new ArrayNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsDenormalization(null, Project::class));
        self::assertFalse($normalizer->supportsDenormalization(null, ProjectId::class));
    }

    #[Test]
    public function normalize_and_denormalizer_works(): void
    {
        // -- Arrange
        $normalizer = new ArrayNormalizableNormalizer();

        $project = new Project(
            projectId: ProjectId::generateRandom(),
            name: 'Great project',
        );

        // -- Act
        $normalized = $normalizer->normalize($project);
        $denormalized = $normalizer->denormalize($normalized, Project::class);

        // -- Assert
        self::assertEquals($project, $denormalized);
    }

    #[Test]
    public function normalize_and_denormalizer_works_with_null(): void
    {
        // -- Arrange
        $normalizer = new ArrayNormalizableNormalizer();

        $project = null;

        // -- Act
        $normalized = $normalizer->normalize($project);
        $denormalized = $normalizer->denormalize($normalized, Project::class);

        // -- Assert
        self::assertEquals($project, $denormalized);
    }
}
