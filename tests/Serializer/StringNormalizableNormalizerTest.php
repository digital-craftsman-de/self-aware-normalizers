<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringNormalizableNormalizer::class)]
final class StringNormalizableNormalizerTest extends TestCase
{
    #[Test]
    public function supports_normalization_works(): void
    {
        // -- Arrange
        $normalizer = new StringNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsNormalization(new SearchTerm('peter')));
        self::assertFalse($normalizer->supportsNormalization(new Limit(50)));
    }

    #[Test]
    public function supports_denormalization_works(): void
    {
        // -- Arrange
        $normalizer = new StringNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsDenormalization(null, SearchTerm::class));
        self::assertFalse($normalizer->supportsDenormalization(null, Limit::class));
    }

    #[Test]
    public function string_normalize_and_denormalizer_works(): void
    {
        // -- Arrange
        $normalizer = new StringNormalizableNormalizer();

        $searchTerm = new SearchTerm('peter');

        // -- Act
        $normalized = $normalizer->normalize($searchTerm);
        $denormalized = $normalizer->denormalize($normalized, SearchTerm::class);

        // -- Assert
        self::assertEquals($searchTerm, $denormalized);
    }

    #[Test]
    public function string_normalize_and_denormalizer_works_with_null(): void
    {
        // -- Arrange
        $normalizer = new StringNormalizableNormalizer();

        $searchTerm = null;

        // -- Act
        $normalized = $normalizer->normalize($searchTerm);
        $denormalized = $normalizer->denormalize($normalized, SearchTerm::class);

        // -- Assert
        self::assertEquals($searchTerm, $denormalized);
    }
}
