<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Range;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FloatNormalizableNormalizer::class)]
final class FloatNormalizableNormalizerTest extends TestCase
{
    #[Test]
    public function supports_normalization_works(): void
    {
        // -- Arrange
        $normalizer = new FloatNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsNormalization(new Range(0.5)));
        self::assertFalse($normalizer->supportsNormalization(ProjectId::generateRandom()));
    }

    #[Test]
    public function supports_denormalization_works(): void
    {
        // -- Arrange
        $normalizer = new FloatNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsDenormalization(null, Range::class));
        self::assertFalse($normalizer->supportsDenormalization(null, ProjectId::class));
    }

    #[Test]
    public function int_normalize_and_denormalizer_works(): void
    {
        // -- Arrange
        $normalizer = new FloatNormalizableNormalizer();

        $range = new Range(0.5);

        // -- Act
        $normalized = $normalizer->normalize($range);
        $denormalized = $normalizer->denormalize($normalized, Range::class);

        // -- Assert
        self::assertEquals($range, $denormalized);
    }

    #[Test]
    public function int_normalize_and_denormalizer_works_with_null(): void
    {
        // -- Arrange
        $normalizer = new FloatNormalizableNormalizer();

        $range = null;

        // -- Act
        $normalized = $normalizer->normalize($range);
        $denormalized = $normalizer->denormalize($normalized, Range::class);

        // -- Assert
        self::assertEquals($range, $denormalized);
    }
}
