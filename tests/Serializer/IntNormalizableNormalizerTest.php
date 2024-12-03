<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntNormalizableNormalizer::class)]
final class IntNormalizableNormalizerTest extends TestCase
{
    #[Test]
    public function supports_normalization_works(): void
    {
        // -- Arrange
        $normalizer = new IntNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsNormalization(new Limit(60)));
        self::assertFalse($normalizer->supportsNormalization(ProjectId::generateRandom()));
    }

    #[Test]
    public function supports_denormalization_works(): void
    {
        // -- Arrange
        $normalizer = new IntNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsDenormalization(null, Limit::class));
        self::assertFalse($normalizer->supportsDenormalization(null, ProjectId::class));
    }

    #[Test]
    public function int_normalize_and_denormalizer_works(): void
    {
        // -- Arrange
        $normalizer = new IntNormalizableNormalizer();

        $limit = new Limit(60);

        // -- Act
        $normalized = $normalizer->normalize($limit);
        $denormalized = $normalizer->denormalize($normalized, Limit::class);

        // -- Assert
        self::assertEquals($limit, $denormalized);
    }

    #[Test]
    public function int_normalize_and_denormalizer_works_with_null(): void
    {
        // -- Arrange
        $normalizer = new IntNormalizableNormalizer();

        $limit = null;

        // -- Act
        $normalized = $normalizer->normalize($limit);
        $denormalized = $normalizer->denormalize($normalized, Limit::class);

        // -- Assert
        self::assertEquals($limit, $denormalized);
    }
}
